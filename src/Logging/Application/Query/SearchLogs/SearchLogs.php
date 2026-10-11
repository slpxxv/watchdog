<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Query\SearchLogs;

final readonly class SearchLogs
{
    public const int DEFAULT_LIMIT = 100;
    public const int MAX_LIMIT = 200;

    public function __construct(
        public string $projectId,
        public LogQueryFilters $filters = new LogQueryFilters(),
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public ?string $cursor = null,
        public int $limit = self::DEFAULT_LIMIT,
    ) {
    }
}
