<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command\ApplyLogRetention;

use Psr\Clock\ClockInterface;
use Watchdog\Logging\Application\Port\LogPartitions;
use Watchdog\Logging\Application\Port\LogRetentionPolicy;

/**
 * Drops whole daily partitions older than the retention: instant, and no bloat left behind
 * the way a DELETE of millions of rows would.
 */
final readonly class ApplyLogRetentionHandler
{
    public function __construct(
        private LogPartitions $partitions,
        private LogRetentionPolicy $retention,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<string> partitions dropped now
     */
    public function __invoke(ApplyLogRetention $command): array
    {
        $days = $this->retention->days();
        if ($days < ApplyLogRetention::MIN_DAYS) {
            throw new \LogicException(\sprintf('LOG_RETENTION_DAYS must be at least %d (got %d): ingestion still accepts lines that old.', ApplyLogRetention::MIN_DAYS, $days));
        }

        // Keeping 14 days means today plus the 13 before it.
        $oldestKept = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0)->modify(\sprintf('-%d days', $days - 1));

        return $this->partitions->dropOlderThan($oldestKept);
    }
}
