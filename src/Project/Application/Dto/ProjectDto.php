<?php

declare(strict_types=1);

namespace Watchdog\Project\Application\Dto;

use Watchdog\Project\Domain\Project;

final readonly class ProjectDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $createdAt,
    ) {
    }

    public static function fromProject(Project $project): self
    {
        return new self(
            id: $project->id()->value,
            name: $project->name(),
            createdAt: $project->createdAt()->format(\DATE_ATOM),
        );
    }
}
