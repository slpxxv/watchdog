<?php

declare(strict_types=1);

namespace Watchdog\Logging\Infrastructure\Project;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Watchdog\Logging\Application\Command\RevokeProjectSources;
use Watchdog\Logging\Application\Message\PurgeProjectLogsMessage;
use Watchdog\Project\Application\Event\ProjectDeleted;

/**
 * Tokens stop working right away; the logs, possibly millions of lines, go in the background.
 */
#[AsEventListener]
final readonly class ProjectDeletedListener
{
    public function __construct(
        private RevokeProjectSources $revokeProjectSources,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ProjectDeleted $event): void
    {
        ($this->revokeProjectSources)($event->projectId);
        $this->bus->dispatch(new PurgeProjectLogsMessage($event->projectId));
    }
}
