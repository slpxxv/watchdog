<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http\Request;

use Watchdog\Logging\Application\Query\LogQueryFilters;
use Watchdog\Logging\Application\Query\SearchLogs;

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

    public function filters(): LogQueryFilters
    {
        return new LogQueryFilters($this->level, $this->sources, $this->q, $this->context);
    }
}
