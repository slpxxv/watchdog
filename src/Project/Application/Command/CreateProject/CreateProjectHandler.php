<?php

declare(strict_types=1);

namespace Watchdog\Project\Application\Command\CreateProject;

use Psr\Clock\ClockInterface;
use Watchdog\Project\Domain\Project;
use Watchdog\Project\Domain\ProjectId;
use Watchdog\Project\Domain\ProjectRepository;

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
