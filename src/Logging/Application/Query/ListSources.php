<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Query;

use Watchdog\Logging\Application\Dto\SourceDto;
use Watchdog\Logging\Application\Port\ProjectCatalog;
use Watchdog\Logging\Domain\Exception\UnknownProject;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\SourceRepository;

final readonly class ListSources
{
    public function __construct(
        private SourceRepository $sources,
        private ProjectCatalog $projects,
    ) {
    }

    /**
     * @return list<SourceDto>
     */
    public function __invoke(string $projectId): array
    {
        $project = ProjectId::fromString($projectId);
        if (!$this->projects->exists($project)) {
            throw UnknownProject::withId($project);
        }

        return array_map(SourceDto::fromSource(...), $this->sources->ofProject($project));
    }
}
