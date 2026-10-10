<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Source;

use Watchdog\Logging\Domain\ProjectId;

interface SourceRepository
{
    public function nextIdentity(): SourceId;

    public function save(Source $source): void;

    public function ofId(SourceId $id): ?Source;

    public function ofTokenHash(string $tokenHash): ?Source;

    /**
     * @return list<Source> oldest first
     */
    public function ofProject(ProjectId $projectId): array;
}
