<?php

declare(strict_types=1);

namespace Watchdog\Project\Application\Query\ProjectExists;

use Watchdog\Project\Domain\ProjectId;
use Watchdog\Project\Domain\ProjectRepository;

final readonly class ProjectExistsHandler
{
    public function __construct(
        private ProjectRepository $projects,
    ) {
    }

    public function __invoke(ProjectExists $query): bool
    {
        return null !== $this->projects->ofId(ProjectId::fromString($query->id));
    }
}
