<?php

declare(strict_types=1);

namespace App\Project\Application\Query\GetProject;

use App\Project\Application\Dto\ProjectDto;
use App\Project\Domain\Exception\ProjectNotFound;
use App\Project\Domain\ProjectId;
use App\Project\Domain\ProjectRepository;

final readonly class GetProjectHandler
{
    public function __construct(
        private ProjectRepository $projects,
    ) {
    }

    public function __invoke(GetProject $query): ProjectDto
    {
        $id = ProjectId::fromString($query->id);
        $project = $this->projects->ofId($id) ?? throw ProjectNotFound::withId($id);

        return ProjectDto::fromProject($project);
    }
}
