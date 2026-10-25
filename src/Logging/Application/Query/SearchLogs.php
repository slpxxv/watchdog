<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Query;

use Psr\Clock\ClockInterface;
use Watchdog\Logging\Application\Dto\LogPageDto;
use Watchdog\Logging\Application\Port\LogRetentionPolicy;
use Watchdog\Logging\Application\Port\ProjectCatalog;
use Watchdog\Logging\Domain\Exception\UnknownProject;
use Watchdog\Logging\Domain\Log\Exception\InvalidLogQuery;
use Watchdog\Logging\Domain\Log\LogCursor;
use Watchdog\Logging\Domain\Log\LogFilter;
use Watchdog\Logging\Domain\Log\LogStore;
use Watchdog\Logging\Domain\ProjectId;

final readonly class SearchLogs
{
    public const int DEFAULT_LIMIT = 100;
    public const int MAX_LIMIT = 200;

    // Without a lower bound the query would read every partition.
    public const string DEFAULT_RANGE = '-24 hours';

    public function __construct(
        private LogStore $logs,
        private ProjectCatalog $projects,
        private LogEntryPresenter $presenter,
        private LogRetentionPolicy $retention,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        string $projectId,
        LogQueryFilters $filters = new LogQueryFilters(),
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        ?string $cursor = null,
        int $limit = self::DEFAULT_LIMIT,
    ): LogPageDto {
        $project = ProjectId::fromString($projectId);
        if (!$this->projects->exists($project)) {
            throw UnknownProject::withId($project);
        }
        if ($limit < 1 || $limit > SearchLogs::MAX_LIMIT) {
            throw InvalidLogQuery::because(\sprintf('The limit must be 1-%d.', SearchLogs::MAX_LIMIT));
        }

        $from = $from ?? ($to ?? $this->clock->now())->modify(self::DEFAULT_RANGE);
        if (null !== $to && $from >= $to) {
            throw InvalidLogQuery::because('"from" must be before "to".');
        }

        try {
            $after = null === $cursor || '' === $cursor ? null : LogCursor::decode($cursor);
        } catch (\InvalidArgumentException) {
            throw InvalidLogQuery::because('The cursor is not valid; start again without it.');
        }

        $page = $this->logs->search(
            new LogFilter(
                projectId: $project,
                from: $from,
                to: $to,
                minLevel: $filters->level(),
                sourceIds: $filters->sources(),
                text: $filters->text(),
                context: $filters->contextObject(),
            ),
            $after,
            $limit,
        );

        return new LogPageDto(
            items: $this->presenter->present($project, $page->entries),
            nextCursor: $page->next?->encode(),
            from: $from->format(\DATE_ATOM),
            to: $to?->format(\DATE_ATOM),
            retentionDays: $this->retention->days(),
        );
    }
}
