<?php

declare(strict_types=1);

namespace Watchdog\Logging\Infrastructure\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;
use Watchdog\Logging\Application\Command\PrepareLogPartitions\PrepareLogPartitions;
use Watchdog\Logging\Application\Command\PrepareLogPartitions\PrepareLogPartitionsHandler;

#[AsPeriodicTask(frequency: '1 day', from: '00:15', jitter: 300)]
final readonly class LogPartitionsTask
{
    public function __construct(
        private PrepareLogPartitionsHandler $preparePartitions,
    ) {
    }

    public function __invoke(): void
    {
        ($this->preparePartitions)(new PrepareLogPartitions());
    }
}
