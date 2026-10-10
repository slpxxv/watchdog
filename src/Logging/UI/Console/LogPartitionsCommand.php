<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Watchdog\Logging\Application\Command\PrepareLogPartitions\PrepareLogPartitions;
use Watchdog\Logging\Application\Command\PrepareLogPartitions\PrepareLogPartitionsHandler;

/**
 * Run on deploy; the scheduler repeats it daily.
 */
#[AsCommand(name: 'app:logs:partitions', description: 'Creates the missing daily log partitions')]
final readonly class LogPartitionsCommand
{
    public function __construct(
        private PrepareLogPartitionsHandler $preparePartitions,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $created = ($this->preparePartitions)(new PrepareLogPartitions());

        $io->success([] === $created ? 'All log partitions are in place.' : 'Created: '.implode(', ', $created));

        return Command::SUCCESS;
    }
}
