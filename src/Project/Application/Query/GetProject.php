<?php

declare(strict_types=1);

namespace Watchdog\Project\Application\Query;

use Watchdog\Project\Application\Dto\ProjectDto;
use Watchdog\Project\Domain\Exception\ProjectNotFound;
use Watchdog\Project\Domain\ProjectId;
use Watchdog\Project\Domain\ProjectRepository;

final readonly class GetProject
{
    public function __construct(
        private ProjectRepository $projects,
    ) {
    }

    public function __invoke(string $id): ProjectDto
    {
        $id = ProjectId::fromString($id);
        $project = $this->projects->ofId($id) ?? throw ProjectNotFound::withId($id);

        return ProjectDto::fromProject($project);
    }
}
