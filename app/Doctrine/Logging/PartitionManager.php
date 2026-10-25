<?php

declare(strict_types=1);

namespace Watchdog\App\Doctrine\Logging;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Psr\Log\LoggerInterface;
use Watchdog\Logging\Application\Port\LogPartitions;

final readonly class PartitionManager implements LogPartitions
{
    private const string DEFAULT_PARTITION = 'log_entry_default';
    private const int COUNT_CAP = 100_000; // enough to raise the alarm without scanning a huge table

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    public function ensureDays(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $utc = new \DateTimeZone('UTC');
        $day = $from->setTimezone($utc)->setTime(0, 0);
        $last = $to->setTimezone($utc)->setTime(0, 0);
        $created = [];

        for (; $day <= $last; $day = $day->modify('+1 day')) {
            $name = 'log_entry_p'.$day->format('Ymd');
            if (null !== $this->connection->fetchOne('SELECT to_regclass(?)', [$name])) {
                continue;
            }

            try {
                // A savepoint: a failed CREATE must not abort a surrounding transaction.
                $this->connection->transactional(fn (Connection $connection): int|string => $connection->executeStatement(\sprintf(
                    "CREATE TABLE IF NOT EXISTS %s PARTITION OF log_entry FOR VALUES FROM ('%s') TO ('%s')",
                    $name,
                    $day->format('Y-m-d 00:00:00+00'),
                    $day->modify('+1 day')->format('Y-m-d 00:00:00+00'),
                )));
                $created[] = $name;
            } catch (DbalException $e) {
                // Most likely the default partition already holds rows for this day; keep going with the rest.
                $this->logger->error('Could not create log partition {partition}: {error}', ['partition' => $name, 'error' => $e->getMessage()]);
            }
        }

        return $created;
    }

    public function dropOlderThan(\DateTimeImmutable $oldestKept): array
    {
        $cutoff = $oldestKept->setTimezone(new \DateTimeZone('UTC'))->format('Ymd');
        $dropped = [];

        foreach ($this->dailyPartitions() as $name => $day) {
            if ($day >= $cutoff) {
                continue;
            }

            try {
                $this->connection->transactional(fn (Connection $connection): int|string => $connection->executeStatement('DROP TABLE '.$name));
                $dropped[] = $name;
            } catch (DbalException $e) {
                $this->logger->error('Could not drop log partition {partition}: {error}', ['partition' => $name, 'error' => $e->getMessage()]);
            }
        }

        return $dropped;
    }

    public function linesInDefaultPartition(): int
    {
        $count = $this->connection->fetchOne(\sprintf(
            'SELECT count(*) FROM (SELECT 1 FROM %s LIMIT %d) capped',
            self::DEFAULT_PARTITION,
            self::COUNT_CAP,
        ));

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * @return array<string, string> partition name => its day as Ymd, oldest first
     */
    private function dailyPartitions(): array
    {
        $partitions = [];
        foreach ($this->connection->fetchFirstColumn(<<<'SQL'
            SELECT c.relname FROM pg_inherits i JOIN pg_class c ON c.oid = i.inhrelid
            WHERE i.inhparent = 'log_entry'::regclass
            SQL) as $name) {
            if (\is_string($name) && 1 === preg_match('/^log_entry_p(\d{8})$/', $name, $m)) {
                $partitions[$name] = $m[1];
            }
        }
        asort($partitions);

        return $partitions;
    }
}
