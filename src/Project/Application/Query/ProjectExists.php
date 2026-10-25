<?php

declare(strict_types=1);

namespace Watchdog\Project\Application\Query;

use Watchdog\Project\Domain\ProjectId;
use Watchdog\Project\Domain\ProjectRepository;

final readonly class ProjectExists
{
    public function __construct(
        private ProjectRepository $projects,
    ) {
    }

    public function __invoke(string $id): bool
    {
        return null !== $this->projects->ofId(ProjectId::fromString($id));
    }
}
