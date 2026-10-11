<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http\Request;

use Watchdog\Logging\Application\Query\SearchLogs\LogQueryFilters;
use Watchdog\Logging\Application\Query\TailLogs\TailLogs;

final readonly class TailLogsRequest
{
    /**
     * @param list<string> $sources
     */
    public function __construct(
        public int $after = 0,
        public ?string $level = null,
        public array $sources = [],
        public ?string $q = null,
        public ?string $context = null,
        public int $limit = TailLogs::MAX_LIMIT,
    ) {
    }

    public function toQuery(string $projectId): TailLogs
    {
        return new TailLogs(
            projectId: $projectId,
            afterId: $this->after,
            filters: new LogQueryFilters($this->level, $this->sources, $this->q, $this->context),
            limit: $this->limit,
        );
    }
}
