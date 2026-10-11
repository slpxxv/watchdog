<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command\IngestLogs;

final readonly class IngestLogs
{
    /**
     * @param list<array<array-key, mixed>> $lines decoded JSON objects, one per log line
     */
    public function __construct(
        public string $sourceId,
        public array $lines,
    ) {
    }
}
