<?php

declare(strict_types=1);

namespace App\Project\Application\Command\RenameProject;

use App\Project\Domain\Exception\ProjectNotFound;
use App\Project\Domain\ProjectId;
use App\Project\Domain\ProjectRepository;

final readonly class RenameProjectHandler
{
    public function __construct(
        private ProjectRepository $projects,
    ) {
    }

    public function __invoke(RenameProject $command): void
    {
        $id = ProjectId::fromString($command->id);
        $project = $this->projects->ofId($id) ?? throw ProjectNotFound::withId($id);

        $project->rename($command->name);

        $this->projects->save($project);
    }
}
