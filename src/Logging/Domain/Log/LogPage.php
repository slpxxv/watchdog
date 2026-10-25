<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Log;

final readonly class LogPage
{
    /**
     * @param list<LogEntry> $entries newest first
     * @param ?LogCursor     $next    null when there is nothing older
     */
    public function __construct(
        public array $entries,
        public ?LogCursor $next,
    ) {
    }
}
