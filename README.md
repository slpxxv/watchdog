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
vendor/bin/php-cs-fixer fix
vendor/bin/phpstan analyse
vendor/bin/deptrac analyse
php bin/phpunit
npm --prefix frontend run build
```

## Architecture

Each bounded context in `src/<Context>/` has four layers, enforced by Deptrac (`deptrac.yaml`):

| Layer | May depend on |
|---|---|
| `Domain` | only `doctrine/collections` (Doctrine mapping is XML in Infrastructure) |
| `Application` | Domain, PSR |
| `Infrastructure` | Domain, Application, Symfony/Doctrine |
| `UI` (Http/Console) | Application, Domain, Symfony |

Contexts: `Identity` (users, roles, login API).

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
├── Infrastructure/              driven adapters: Doctrine repositories + XML mapping, Symfony Security
└── UI/                          driving adapters: Http (thin controllers), Console
```

- A request goes **UI → handler → Domain**, and comes back as a **DTO**: `SecurityController` reads the actor
  from the `CurrentActor` port, calls `GetUserHandler`, returns its `UserDto` as JSON.
- Ports are interfaces next to the code that needs them (repositories in `Domain`, the rest in
  `Application/Port`); adapters in `Infrastructure` implement them and autowiring binds them.
- Handlers are plain invokable services (`($handler)($command)`), not Messenger handlers.
- Doctrine mapping files are named after the class path below `Domain`: `User.User.orm.xml`.
