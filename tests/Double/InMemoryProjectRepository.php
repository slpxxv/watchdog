<?php

declare(strict_types=1);

namespace Watchdog\Tests\Double;

use Watchdog\Project\Domain\Project;
use Watchdog\Project\Domain\ProjectId;
use Watchdog\Project\Domain\ProjectRepository;

final class InMemoryProjectRepository implements ProjectRepository
{
    /** @var array<string, Project> */
    private array $projects = [];
    private int $sequence = 0;

    public function nextIdentity(): ProjectId
    {
        return ProjectId::fromString(\sprintf('00000000-0000-7000-a000-%012d', ++$this->sequence));
    }

    public function ofId(ProjectId $id): ?Project
    {
        return $this->projects[$id->value] ?? null;
    }

    public function save(Project $project): void
    {
        $this->projects[$project->id()->value] = $project;
    }

    public function all(): array
    {
        return array_values($this->projects);
    }
}
