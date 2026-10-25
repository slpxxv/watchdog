<?php

declare(strict_types=1);

namespace Watchdog\App\Request\Logging;

use Watchdog\Logging\Application\Query\SearchLogs\LogQueryFilters;
use Watchdog\Logging\Application\Query\SearchLogs\SearchLogs;

/**
 * Query string of a log search; the handler validates the values (one place for the rules).
 */
final readonly class SearchLogsRequest
{
    /**
     * @param list<string> $sources
     */
    public function __construct(
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public ?string $level = null,
        public array $sources = [],
        public ?string $q = null,
        public ?string $context = null,
        public ?string $cursor = null,
        public int $limit = SearchLogs::DEFAULT_LIMIT,
    ) {
    }

    public function toQuery(string $projectId): SearchLogs
    {
        return new SearchLogs(
            projectId: $projectId,
            filters: new LogQueryFilters($this->level, $this->sources, $this->q, $this->context),
            from: $this->from,
            to: $this->to,
            cursor: $this->cursor,
            limit: $this->limit,
        );
    }
}
