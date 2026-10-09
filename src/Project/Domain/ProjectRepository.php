<?php

declare(strict_types=1);

namespace Watchdog\Project\Domain;

interface ProjectRepository
{
    public function nextIdentity(): ProjectId;

    public function ofId(ProjectId $id): ?Project;

    public function save(Project $project): void;

    public function remove(Project $project): void;

    /**
     * @return list<Project>
     */
    public function all(): array;
}
