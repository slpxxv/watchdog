<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Query;

use Psr\Clock\ClockInterface;
use Watchdog\Logging\Application\Dto\TailDto;
use Watchdog\Logging\Application\Port\ProjectCatalog;
use Watchdog\Logging\Domain\Exception\UnknownProject;
use Watchdog\Logging\Domain\Log\Exception\InvalidLogQuery;
use Watchdog\Logging\Domain\Log\LogEntry;
use Watchdog\Logging\Domain\Log\LogFilter;
use Watchdog\Logging\Domain\Log\LogStore;
use Watchdog\Logging\Domain\ProjectId;

final readonly class TailLogs
{
    public const int MAX_LIMIT = 500;

    public function __construct(
        private LogStore $logs,
        private ProjectCatalog $projects,
        private LogEntryPresenter $presenter,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        string $projectId,
        int $afterId = 0,
        LogQueryFilters $filters = new LogQueryFilters(),
        int $limit = self::MAX_LIMIT,
    ): TailDto {
        $project = ProjectId::fromString($projectId);
        if (!$this->projects->exists($project)) {
            throw UnknownProject::withId($project);
        }
        if ($afterId < 0) {
            throw InvalidLogQuery::because('"after" cannot be negative.');
        }
        if ($limit < 1 || $limit > TailLogs::MAX_LIMIT) {
            throw InvalidLogQuery::because(\sprintf('The limit must be 1-%d.', TailLogs::MAX_LIMIT));
        }

        $entries = $this->logs->tail(
            new LogFilter(
                projectId: $project,
                minLevel: $filters->level(),
                sourceIds: $filters->sources(),
                text: $filters->text(),
                context: $filters->contextObject(),
            ),
            $afterId,
            $this->clock->now(),
            $limit,
        );

        $lastId = array_reduce($entries, static fn (int $max, LogEntry $e): int => max($max, (int) $e->id), $afterId);

        return new TailDto(
            items: $this->presenter->present($project, $entries),
            lastId: $lastId,
        );
    }
}
