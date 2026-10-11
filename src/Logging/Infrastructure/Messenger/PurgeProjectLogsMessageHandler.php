<?php

declare(strict_types=1);

namespace Watchdog\Logging\Infrastructure\Messenger;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Watchdog\Logging\Application\Command\PurgeProjectLogs\PurgeProjectLogs;
use Watchdog\Logging\Application\Command\PurgeProjectLogs\PurgeProjectLogsHandler;

/**
 * Messenger entry point; the Application handler stays free of framework attributes.
 */
#[AsMessageHandler]
final readonly class PurgeProjectLogsMessageHandler
{
    public function __construct(
        private PurgeProjectLogsHandler $purgeProjectLogs,
    ) {
    }

    public function __invoke(PurgeProjectLogs $message): void
    {
        ($this->purgeProjectLogs)($message);
    }
}
