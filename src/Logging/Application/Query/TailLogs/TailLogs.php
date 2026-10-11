<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Query\TailLogs;

use Watchdog\Logging\Application\Query\SearchLogs\LogQueryFilters;

final readonly class TailLogs
{
    public const int MAX_LIMIT = 500;

    public function __construct(
        public string $projectId,
        public int $afterId = 0,
        public LogQueryFilters $filters = new LogQueryFilters(),
        public int $limit = self::MAX_LIMIT,
    ) {
    }
}
