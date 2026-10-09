<?php

declare(strict_types=1);

namespace App\Project\Application\Command\CreateProject;

use App\Project\Domain\Project;
use App\Project\Domain\ProjectId;
use App\Project\Domain\ProjectRepository;
use Psr\Clock\ClockInterface;

final readonly class CreateProjectHandler
{
    public function __construct(
        private ProjectRepository $projects,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateProject $command): ProjectId
    {
        $project = Project::create(
            id: $this->projects->nextIdentity(),
            name: $command->name,
            createdAt: $this->clock->now(),
        );

        $this->projects->save($project);

        return $project->id();
    }
}
