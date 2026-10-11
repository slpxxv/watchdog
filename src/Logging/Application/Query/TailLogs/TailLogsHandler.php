<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Query\TailLogs;

use Psr\Clock\ClockInterface;
use Watchdog\Logging\Application\Dto\TailDto;
use Watchdog\Logging\Application\Port\ProjectCatalog;
use Watchdog\Logging\Application\Query\LogEntryPresenter;
use Watchdog\Logging\Domain\Exception\UnknownProject;
use Watchdog\Logging\Domain\Log\Exception\InvalidLogQuery;
use Watchdog\Logging\Domain\Log\LogEntry;
use Watchdog\Logging\Domain\Log\LogFilter;
use Watchdog\Logging\Domain\Log\LogStore;
use Watchdog\Logging\Domain\ProjectId;

final readonly class TailLogsHandler
{
    public function __construct(
        private LogStore $logs,
        private ProjectCatalog $projects,
        private LogEntryPresenter $presenter,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(TailLogs $query): TailDto
    {
        $projectId = ProjectId::fromString($query->projectId);
        if (!$this->projects->exists($projectId)) {
            throw UnknownProject::withId($projectId);
        }
        if ($query->afterId < 0) {
            throw InvalidLogQuery::because('"after" cannot be negative.');
        }
        if ($query->limit < 1 || $query->limit > TailLogs::MAX_LIMIT) {
            throw InvalidLogQuery::because(\sprintf('The limit must be 1-%d.', TailLogs::MAX_LIMIT));
        }

        $entries = $this->logs->tail(
            new LogFilter(
                projectId: $projectId,
                minLevel: $query->filters->level(),
                sourceIds: $query->filters->sources(),
                text: $query->filters->text(),
                context: $query->filters->contextObject(),
            ),
            $query->afterId,
            $this->clock->now(),
            $query->limit,
        );

        $lastId = array_reduce($entries, static fn (int $max, LogEntry $e): int => max($max, (int) $e->id), $query->afterId);

        return new TailDto(
            items: $this->presenter->present($projectId, $entries),
            lastId: $lastId,
        );
    }
}
