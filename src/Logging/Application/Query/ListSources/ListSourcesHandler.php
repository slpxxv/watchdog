<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Query\ListSources;

use Watchdog\Logging\Application\Dto\SourceDto;
use Watchdog\Logging\Application\Port\ProjectCatalog;
use Watchdog\Logging\Domain\Exception\UnknownProject;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\SourceRepository;

final readonly class ListSourcesHandler
{
    public function __construct(
        private SourceRepository $sources,
        private ProjectCatalog $projects,
    ) {
    }

    /**
     * @return list<SourceDto>
     */
    public function __invoke(ListSources $query): array
    {
        $projectId = ProjectId::fromString($query->projectId);
        if (!$this->projects->exists($projectId)) {
            throw UnknownProject::withId($projectId);
        }

        return array_map(SourceDto::fromSource(...), $this->sources->ofProject($projectId));
    }
}
