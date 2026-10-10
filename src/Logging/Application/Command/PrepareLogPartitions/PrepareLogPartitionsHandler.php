<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command\PrepareLogPartitions;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Watchdog\Logging\Application\Port\LogPartitions;

/**
 * Keeps daily partitions ready ahead of time. Postgres refuses to attach a partition for a range the
 * default partition already holds rows for, so they must exist before any line for that day arrives.
 */
final readonly class PrepareLogPartitionsHandler
{
    public function __construct(
        private LogPartitions $partitions,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<string> partitions created now
     */
    public function __invoke(PrepareLogPartitions $command): array
    {
        $today = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0);

        $created = $this->partitions->ensureDays(
            $today->modify(\sprintf('-%d days', PrepareLogPartitions::DAYS_BACK)),
            $today->modify(\sprintf('+%d days', PrepareLogPartitions::DAYS_AHEAD)),
        );

        $stray = $this->partitions->linesInDefaultPartition();
        if ($stray > 0) {
            $this->logger->warning('{count} log lines sit in the default partition; their days have no partition of their own.', ['count' => $stray]);
        }

        return $created;
    }
}
