<?php

declare(strict_types=1);

namespace Watchdog\Logging\Infrastructure\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;
use Watchdog\Logging\Application\Command\ApplyLogRetention;
use Watchdog\Logging\Application\Command\PrepareLogPartitions;

/**
 * Daily: partitions for the coming days, then retention drops the oldest ones.
 */
#[AsPeriodicTask(frequency: '1 day', from: '00:15', jitter: 300)]
final readonly class LogMaintenanceTask
{
    public function __construct(
        private PrepareLogPartitions $preparePartitions,
        private ApplyLogRetention $applyRetention,
    ) {
    }

    public function __invoke(): void
    {
        ($this->preparePartitions)();
        ($this->applyRetention)();
    }
}
