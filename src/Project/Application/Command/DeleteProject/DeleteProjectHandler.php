<?php

declare(strict_types=1);

namespace Watchdog\Project\Application\Command\DeleteProject;

use Psr\EventDispatcher\EventDispatcherInterface;
use Watchdog\Project\Application\Event\ProjectDeleted;
use Watchdog\Project\Domain\Exception\ProjectNotFound;
use Watchdog\Project\Domain\ProjectId;
use Watchdog\Project\Domain\ProjectRepository;

final readonly class DeleteProjectHandler
{
    public function __construct(
        private ProjectRepository $projects,
        private EventDispatcherInterface $events,
    ) {
    }

    public function __invoke(DeleteProject $command): void
    {
        $id = ProjectId::fromString($command->id);
        $project = $this->projects->ofId($id) ?? throw ProjectNotFound::withId($id);

        $this->projects->remove($project);
        $this->events->dispatch(new ProjectDeleted($id->value));
    }
}
