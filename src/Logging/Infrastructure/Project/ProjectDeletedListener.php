<?php

declare(strict_types=1);

namespace Watchdog\Logging\Infrastructure\Project;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Watchdog\Logging\Application\Command\PurgeProjectLogs\PurgeProjectLogs;
use Watchdog\Logging\Application\Command\RevokeProjectSources\RevokeProjectSources;
use Watchdog\Logging\Application\Command\RevokeProjectSources\RevokeProjectSourcesHandler;
use Watchdog\Project\Application\Event\ProjectDeleted;

/**
 * Tokens stop working right away; the logs, possibly millions of lines, go in the background.
 */
#[AsEventListener]
final readonly class ProjectDeletedListener
{
    public function __construct(
        private RevokeProjectSourcesHandler $revokeProjectSources,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ProjectDeleted $event): void
    {
        ($this->revokeProjectSources)(new RevokeProjectSources($event->projectId));
        $this->bus->dispatch(new PurgeProjectLogs($event->projectId));
    }
}
