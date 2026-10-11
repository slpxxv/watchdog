<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Query\SearchLogs;

use Psr\Clock\ClockInterface;
use Watchdog\Logging\Application\Dto\LogPageDto;
use Watchdog\Logging\Application\Port\ProjectCatalog;
use Watchdog\Logging\Application\Query\LogEntryPresenter;
use Watchdog\Logging\Domain\Exception\UnknownProject;
use Watchdog\Logging\Domain\Log\Exception\InvalidLogQuery;
use Watchdog\Logging\Domain\Log\LogCursor;
use Watchdog\Logging\Domain\Log\LogFilter;
use Watchdog\Logging\Domain\Log\LogStore;
use Watchdog\Logging\Domain\ProjectId;

final readonly class SearchLogsHandler
{
    // Without a lower bound the query would read every partition.
    public const string DEFAULT_RANGE = '-24 hours';

    public function __construct(
        private LogStore $logs,
        private ProjectCatalog $projects,
        private LogEntryPresenter $presenter,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SearchLogs $query): LogPageDto
    {
        $projectId = ProjectId::fromString($query->projectId);
        if (!$this->projects->exists($projectId)) {
            throw UnknownProject::withId($projectId);
        }
        if ($query->limit < 1 || $query->limit > SearchLogs::MAX_LIMIT) {
            throw InvalidLogQuery::because(\sprintf('The limit must be 1-%d.', SearchLogs::MAX_LIMIT));
        }

        $from = $query->from ?? ($query->to ?? $this->clock->now())->modify(self::DEFAULT_RANGE);
        if (null !== $query->to && $from >= $query->to) {
            throw InvalidLogQuery::because('"from" must be before "to".');
        }

        try {
            $cursor = null === $query->cursor || '' === $query->cursor ? null : LogCursor::decode($query->cursor);
        } catch (\InvalidArgumentException) {
            throw InvalidLogQuery::because('The cursor is not valid; start again without it.');
        }

        $page = $this->logs->search(
            new LogFilter(
                projectId: $projectId,
                from: $from,
                to: $query->to,
                minLevel: $query->filters->level(),
                sourceIds: $query->filters->sources(),
                text: $query->filters->text(),
                context: $query->filters->contextObject(),
            ),
            $cursor,
            $query->limit,
        );

        return new LogPageDto(
            items: $this->presenter->present($projectId, $page->entries),
            nextCursor: $page->next?->encode(),
            from: $from->format(\DATE_ATOM),
            to: $query->to?->format(\DATE_ATOM),
        );
    }
}
