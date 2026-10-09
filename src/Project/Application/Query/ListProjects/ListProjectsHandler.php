<?php

declare(strict_types=1);

namespace App\Project\Application\Query\ListProjects;

use App\Project\Application\Dto\ProjectDto;
use App\Project\Domain\ProjectRepository;

final readonly class ListProjectsHandler
{
    public function __construct(
        private ProjectRepository $projects,
    ) {
    }

    /**
     * @return list<ProjectDto>
     */
    public function __invoke(ListProjects $query): array
    {
        return array_map(ProjectDto::fromProject(...), $this->projects->all());
    }
}
