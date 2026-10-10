<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Port;

/**
 * Daily partitions of the log table. Lines whose day has no partition land in the default one.
 */
interface LogPartitions
{
    /**
     * Creates the missing daily partitions for every day from $from to $to (inclusive, UTC).
     *
     * @return list<string> names of the partitions created now
     */
    public function ensureDays(\DateTimeImmutable $from, \DateTimeImmutable $to): array;

    public function linesInDefaultPartition(): int;
}
