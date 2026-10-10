<?php

declare(strict_types=1);

namespace Watchdog\Logging\Infrastructure\Project;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Watchdog\Logging\Application\Command\RevokeProjectSources\RevokeProjectSources;
use Watchdog\Logging\Application\Command\RevokeProjectSources\RevokeProjectSourcesHandler;
use Watchdog\Project\Application\Event\ProjectDeleted;

#[AsEventListener]
final readonly class ProjectDeletedListener
{
    public function __construct(
        private RevokeProjectSourcesHandler $revokeProjectSources,
    ) {
    }

    public function __invoke(ProjectDeleted $event): void
    {
        ($this->revokeProjectSources)(new RevokeProjectSources($event->projectId));
    }
}
