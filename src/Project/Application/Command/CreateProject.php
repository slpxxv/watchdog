<?php

declare(strict_types=1);

namespace Watchdog\Project\Application\Command;

use Psr\Clock\ClockInterface;
use Watchdog\Project\Domain\Project;
use Watchdog\Project\Domain\ProjectId;
use Watchdog\Project\Domain\ProjectRepository;

final readonly class CreateProject
{
    public function __construct(
        private ProjectRepository $projects,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(string $name): ProjectId
    {
        $project = Project::create(
            id: $this->projects->nextIdentity(),
            name: $name,
            createdAt: $this->clock->now(),
        );

        $this->projects->save($project);

        return $project->id();
    }
}
