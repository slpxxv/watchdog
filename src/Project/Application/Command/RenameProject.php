<?php

declare(strict_types=1);

namespace Watchdog\Project\Application\Command;

use Watchdog\Project\Domain\Exception\ProjectNotFound;
use Watchdog\Project\Domain\ProjectId;
use Watchdog\Project\Domain\ProjectRepository;

final readonly class RenameProject
{
    public function __construct(
        private ProjectRepository $projects,
    ) {
    }

    public function __invoke(string $id, string $name): void
    {
        $id = ProjectId::fromString($id);
        $project = $this->projects->ofId($id) ?? throw ProjectNotFound::withId($id);

        $project->rename($name);

        $this->projects->save($project);
    }
}
