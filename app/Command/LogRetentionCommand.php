<?php

declare(strict_types=1);

namespace Watchdog\App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Watchdog\Logging\Application\Command\ApplyLogRetention\ApplyLogRetention;
use Watchdog\Logging\Application\Command\ApplyLogRetention\ApplyLogRetentionHandler;

/**
 * The scheduler runs this daily; the command is for running it by hand.
 */
#[AsCommand(name: 'app:logs:retention', description: 'Drops log partitions older than LOG_RETENTION_DAYS')]
final readonly class LogRetentionCommand
{
    public function __construct(
        private ApplyLogRetentionHandler $applyRetention,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $dropped = ($this->applyRetention)(new ApplyLogRetention());

        $io->success([] === $dropped ? 'Nothing older than the retention.' : 'Dropped: '.implode(', ', $dropped));

        return Command::SUCCESS;
    }
}
