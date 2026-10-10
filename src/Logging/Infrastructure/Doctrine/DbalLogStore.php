<?php

declare(strict_types=1);

namespace Watchdog\Logging\Infrastructure\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Watchdog\Logging\Domain\Log\LogCursor;
use Watchdog\Logging\Domain\Log\LogEntry;
use Watchdog\Logging\Domain\Log\LogFilter;
use Watchdog\Logging\Domain\Log\LogLevel;
use Watchdog\Logging\Domain\Log\LogPage;
use Watchdog\Logging\Domain\Log\LogStore;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\SourceId;

/**
 * Plain DBAL on purpose: the unit of work and hydration of the ORM only cost time at log volumes.
 */
final readonly class DbalLogStore implements LogStore
{
    private const int INSERT_BATCH = 500; // 7 parameters per row, far below the 65 535 limit
    private const string TIMESTAMP = 'Y-m-d H:i:s.uP';
    private const string COLUMNS = 'id, project_id, source_id, timestamp, received_at, level, message, context';
    private const string TAIL_WINDOW = '-1 hour';
    // ponytail: 10 s covers ingest transactions that take milliseconds; revisit if writes go through a queue.
    private const string TAIL_OVERLAP = '-10 seconds';

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function append(array $entries): void
    {
        if ([] === $entries) {
            return;
        }

        $this->connection->transactional(function (Connection $connection) use ($entries): void {
            foreach (array_chunk($entries, self::INSERT_BATCH) as $batch) {
                $params = [];
                foreach ($batch as $entry) {
                    array_push(
                        $params,
                        $entry->projectId->value,
                        $entry->sourceId->value,
                        $entry->timestamp->format(self::TIMESTAMP),
                        $entry->receivedAt->format(self::TIMESTAMP),
                        $entry->level->value,
                        $entry->message,
                        json_encode((object) $entry->context, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION),
                    );
                }

                $connection->executeStatement(
                    'INSERT INTO log_entry (project_id, source_id, timestamp, received_at, level, message, context) VALUES '
                    .implode(', ', array_fill(0, \count($batch), '(?, ?, ?, ?, ?, ?, ?)')),
                    $params,
                );
            }
        });
    }

    public function search(LogFilter $filter, ?LogCursor $cursor, int $limit): LogPage
    {
        [$where, $params, $types] = $this->conditions($filter);

        if (null !== $filter->from) {
            $where[] = 'timestamp >= :from';
            $params['from'] = $filter->from->format(self::TIMESTAMP);
        }
        if (null !== $filter->to) {
            $where[] = 'timestamp < :to';
            $params['to'] = $filter->to->format(self::TIMESTAMP);
        }
        if (null !== $cursor) {
            $where[] = '(timestamp, id) < (:cursorTimestamp, :cursorId)';
            $params['cursorTimestamp'] = $cursor->timestamp->format(self::TIMESTAMP);
            $params['cursorId'] = $cursor->id;
            $types['cursorId'] = ParameterType::INTEGER;
        }

        // One extra row tells whether an older page exists.
        $params['limit'] = $limit + 1;
        $types['limit'] = ParameterType::INTEGER;

        $entries = array_map($this->hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM log_entry WHERE '.implode(' AND ', $where).' ORDER BY timestamp DESC, id DESC LIMIT :limit',
            $params,
            $types,
        ));

        if (\count($entries) <= $limit) {
            return new LogPage($entries, null);
        }

        $entries = \array_slice($entries, 0, $limit);

        return new LogPage($entries, LogCursor::after($entries[$limit - 1]));
    }

    public function tail(LogFilter $filter, int $afterId, \DateTimeImmutable $now, int $limit): array
    {
        [$where, $params, $types] = $this->conditions($filter);

        // The timestamp bound lets Postgres skip every partition older than an hour.
        $where[] = 'timestamp >= :tailSince';
        $where[] = '(id > :afterId OR received_at >= :recentSince)';
        $params += [
            'tailSince' => $now->modify(self::TAIL_WINDOW)->format(self::TIMESTAMP),
            'afterId' => $afterId,
            'recentSince' => $now->modify(self::TAIL_OVERLAP)->format(self::TIMESTAMP),
            'limit' => $limit,
        ];
        $types += ['afterId' => ParameterType::INTEGER, 'limit' => ParameterType::INTEGER];

        return array_map($this->hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM log_entry WHERE '.implode(' AND ', $where).' ORDER BY id ASC LIMIT :limit',
            $params,
            $types,
        ));
    }

    public function deleteProjectBatch(ProjectId $projectId, int $limit): int
    {
        // By primary key, not ctid: ctid is only unique within one partition.
        return (int) $this->connection->executeStatement(
            'DELETE FROM log_entry WHERE (timestamp, id) IN (SELECT timestamp, id FROM log_entry WHERE project_id = :projectId LIMIT :limit)',
            ['projectId' => $projectId->value, 'limit' => $limit],
            ['limit' => ParameterType::INTEGER],
        );
    }

    /**
     * Filters shared by search and tail (everything except time bounds).
     *
     * @return array{list<string>, array<string, mixed>, array<string, ParameterType|ArrayParameterType>}
     */
    private function conditions(LogFilter $filter): array
    {
        $where = ['project_id = :projectId'];
        $params = ['projectId' => $filter->projectId->value];
        $types = [];

        if (null !== $filter->minLevel) {
            $where[] = 'level >= :minLevel';
            $params['minLevel'] = $filter->minLevel->value;
            $types['minLevel'] = ParameterType::INTEGER;
        }
        if ([] !== $filter->sourceIds) {
            $where[] = 'source_id IN (:sourceIds)';
            $params['sourceIds'] = array_map(static fn (SourceId $id): string => $id->value, $filter->sourceIds);
            $types['sourceIds'] = ArrayParameterType::STRING;
        }
        if (null !== $filter->text && '' !== $filter->text) {
            // Served by the trigram index; the user's text is matched literally.
            $where[] = 'message ILIKE :text';
            $params['text'] = '%'.addcslashes($filter->text, '%_\\').'%';
        }
        if (null !== $filter->context) {
            $where[] = 'context @> CAST(:context AS jsonb)';
            $params['context'] = json_encode((object) $filter->context, \JSON_THROW_ON_ERROR);
        }

        return [$where, $params, $types];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): LogEntry
    {
        /** @var array<string, mixed> $context */
        $context = json_decode(self::string($row, 'context'), true, flags: \JSON_THROW_ON_ERROR);

        return new LogEntry(
            projectId: ProjectId::fromString(self::string($row, 'project_id')),
            sourceId: SourceId::fromString(self::string($row, 'source_id')),
            timestamp: new \DateTimeImmutable(self::string($row, 'timestamp')),
            receivedAt: new \DateTimeImmutable(self::string($row, 'received_at')),
            level: LogLevel::from(self::int($row, 'level')),
            message: self::string($row, 'message'),
            context: $context,
            id: self::int($row, 'id'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function string(array $row, string $column): string
    {
        return \is_string($row[$column] ?? null) ? $row[$column] : throw new \UnexpectedValueException(\sprintf('Column "%s" is not a string.', $column));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function int(array $row, string $column): int
    {
        $value = $row[$column] ?? null;

        return match (true) {
            \is_int($value) => $value,
            \is_string($value) && ctype_digit($value) => (int) $value,
            default => throw new \UnexpectedValueException(\sprintf('Column "%s" is not an integer.', $column)),
        };
    }
}
