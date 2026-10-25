# Watchdog

## Start (dev)

Needs PHP 8.4 (`pdo_pgsql`, `redis`, `intl`), the [Symfony CLI](https://symfony.com/download), Node 22.12+ and Docker
(only for Postgres and Redis, `compose.yaml`).

```bash
composer install && npm --prefix frontend install
symfony serve -d
symfony console doctrine:migrations:migrate -n
```


## Quality

```bash
composer qa                     # lints, php-cs-fixer, PHPStan, Deptrac, then PHPUnit (needs the test DB)
npm --prefix frontend run qa    # vue-tsc, ESLint, Prettier, Vitest
```

CI runs the same commands (`.github/workflows/ci.yaml`); its one required check is "CI OK".

## Architecture

The repository is split in two, enforced by Deptrac (`deptrac.yaml`):

- `src/<Context>/` is the domain: plain PHP, no framework. Each bounded context has `Domain` and `Application`.
- `app/` is the Symfony application: kernel, controllers, Doctrine, security, messaging. It adapts the framework
  to `src/` and may use anything. `src/` never depends on `app/`.

| Layer | May depend on |
|---|---|
| `Domain` | only `doctrine/collections` (Doctrine mapping is XML in `app/Doctrine/<Context>/Mapping`) |
| `Application` | Domain, PSR |
| `app/` | Domain, Application, Symfony/Doctrine, PSR |

Contexts: `Identity` (users, roles, login API), `Project` (projects), `Logging` (log sources, ingestion, search,
live tail, retention). Contexts talk only through each other's `Application/Query`, `Dto` and `Event` (`deptrac.contexts.yaml`).

### Inside a context (DDD + hexagonal)

```
src/Identity/
├── Domain/                      the model, plain PHP
│   ├── User/                    aggregate: User, UserId, Email, UserRepository (port), Exception/
│   ├── Role/                    aggregate: Role, RoleId, RoleRepository (port), Exception/
│   └── Acl/                     Permission catalogue, PrivilegeEscalation
├── Application/                 use cases
│   ├── Command/<UseCase>/       input DTO (command) + handler; changes state
│   ├── Query/<UseCase>/         input DTO (query) + handler; returns an output DTO
│   ├── Dto/                     output DTOs (read models: scalars only, never aggregates)
│   ├── Service/                 application services shared by handlers (Actor, RoleResolver)
│   ├── Port/                    driven ports the app needs from outside (PasswordHasher, CurrentActor)
│   └── Exception/               application-level errors (WeakPassword)
└── Application/Port/            what the use cases need from outside
```

and, by technical type, in `app/`:

```
app/
├── Kernel.php
├── Controller/<Context>/        thin controllers
├── Request/<Context>/           request DTOs (#[MapRequestPayload], #[MapQueryString])
├── Doctrine/<Context>/          repositories, DBAL stores, XML mapping (Mapping/)
├── Security/<Context>/          voters, users, token handlers
├── Command/                     console commands
├── Messaging/                   Messenger handlers, Scheduler, event listeners
├── Http/                        cross-cutting HTTP: error listener, problem+json
└── Adapter/<Context>/           implementations of Application ports
```

- A request goes **controller → handler → Domain**, and comes back as a **DTO**: `SecurityController` reads the actor
  from the `CurrentActor` port, calls `GetUserHandler`, returns its `UserDto` as JSON.
- Ports are interfaces next to the code that needs them (repositories in `Domain`, the rest in
  `Application/Port`); adapters in `app/` implement them and autowiring binds them.
- Handlers are plain invokable services (`($handler)($command)`), not Messenger handlers.
- Doctrine mapping files are named after the class path below `Domain`: `User.User.orm.xml`.

## Sending logs

Each project has **sources**: an app, a server or a shipper. Creating one (project page → Źródła) shows its token
once; only a hash is stored. Send logs with that token:

```
POST /api/v1/ingest/logs
Authorization: Bearer wd_…
```

**Body**: a JSON array of objects, a single object, or NDJSON (one object per line); optionally
`Content-Encoding: gzip`. The format is read from the body, so the `Content-Type` does not matter.

**Fields** are read the way common tools name them; anything else is kept as context.

| Meaning | Accepted keys | Notes |
|---|---|---|
| message | `message`, `msg`, `log` | non-strings are converted |
| level | `level`, `severity`, `level_name`, `lvl` | PSR-3 names and aliases (`warn`, `fatal`…), Monolog numbers (100–600), syslog 0–7; unknown → `info` |
| time | `timestamp`, `@timestamp`, `time`, `datetime`, `ts` | ISO 8601 (no offset = UTC) or epoch seconds/milliseconds |
| context | `context` plus every other top-level field | must be an object |

Ingestion is lenient: a bad line is fixed, not rejected, and each fix is noted in the line's context
(`_original_level`, `_original_timestamp`, `_truncated`, `_nul_replaced`, `_dropped`). A timestamp that is
unreadable or outside *now − 7 days … now + 1 hour* is replaced by the time of receipt.

**Response**: `202 {"accepted": 2, "normalized": 0}`.

| Limit | Value | Over the limit |
|---|---|---|
| Body (after gzip) | 2 MB | 413 |
| Lines per request | 10 000 | 413 |
| `message` | 8 KB | cut, `_truncated` |
| context | 16 KB of JSON | replaced by `_dropped` |
| Requests per source | bursts of 100, refilled at 50/s | 429 with `Retry-After` |

Other answers, all as `application/problem+json`: 400 broken JSON, 401 missing, unknown or revoked token,
415 an encoding other than gzip, 422 a line that is not a JSON object. Retry 429 and 5xx; the others will not
succeed on retry.

### curl

```bash
curl -X POST https://watchdog.example.com/api/v1/ingest/logs \
  -H "Authorization: Bearer $WATCHDOG_TOKEN" \
  -d '[{"level": "error", "message": "Payment failed", "context": {"order": 1183}}]'

printf '%s\n' '{"level":"info","message":"one"}' '{"level":"warn","message":"two"}' | gzip |
  curl -X POST https://watchdog.example.com/api/v1/ingest/logs \
    -H "Authorization: Bearer $WATCHDOG_TOKEN" \
    -H 'Content-Encoding: gzip' --data-binary @-
```

### Vector

```yaml
sinks:
  watchdog:
    type: http
    inputs: [app]
    uri: https://watchdog.example.com/api/v1/ingest/logs
    compression: gzip
    encoding:
      codec: json
    framing:
      method: newline_delimited
    auth:
      strategy: bearer
      token: "${WATCHDOG_TOKEN}"
    batch:
      max_events: 1000
      max_bytes: 1000000 # under the 2 MB limit: Vector does not retry the 413 a bigger batch would get
```

Vector retries 429 and 5xx on its own.

### Fluent Bit

```ini
[OUTPUT]
    Name             http
    Match            *
    Host             watchdog.example.com
    Port             443
    tls              On
    URI              /api/v1/ingest/logs
    Format           json_lines
    Compress         gzip
    Header           Authorization Bearer ${WATCHDOG_TOKEN}
    json_date_key    timestamp
    json_date_format iso8601
```

Fluent Bit sends whatever piled up since the last flush in one request, so keep one flush under 10 000 lines
and 2 MB: the default one-second `Flush` is enough for most apps. For heavier traffic, Vector's `batch` limits
are the safer choice.

### PHP apps (Monolog)

Write JSON lines to a stream and let a shipper send them, rather than calling the API from inside a request:
the shipper buffers and retries, and a slow or failing Watchdog never slows down or breaks the app. With
Symfony's monolog-bundle:

```yaml
monolog:
  handlers:
    main:
      type: stream
      path: php://stderr        # or a file the shipper tails
      level: info
      formatter: monolog.formatter.json
```

Monolog's JSON (`message`, `level`, `level_name`, `datetime`, `channel`, `context`, `extra`) is read as is.
When the shipper adds its own time field, prefer the app's, e.g. in Vector:

```yaml
transforms:
  app_json:
    type: remap
    inputs: [app_file]
    source: |
      parsed, err = parse_json(.message)
      if err == null && is_object(parsed) { . = merge(., object!(parsed)) }
      if exists(.datetime) { .timestamp = .datetime }
```

## Running in the background

`symfony serve` starts a worker (`.symfony.local.yaml`) for the `async` queue and the scheduler; in production,
run `bin/console messenger:consume async scheduler_default` under a process manager.

- **Partitions.** `log_entry` is partitioned by day. Run `bin/console app:logs:partitions` on every deploy; the
  scheduler then keeps partitions 7 days ahead daily and warns when lines land in the default partition.
- **Retention.** `LOG_RETENTION_DAYS` (default 14, at least 8) keeps today plus the days before it; the daily
  task drops older partitions. `bin/console app:logs:retention` runs it by hand.
- **Deleting a project** revokes its tokens at once and queues the removal of its logs for the worker.
