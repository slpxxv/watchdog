<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command\IngestLogs;

use Watchdog\Logging\Domain\Log\LogEntry;

final readonly class NormalizedLine
{
    public function __construct(
        public LogEntry $entry,
        public bool $wasFixed,
    ) {
    }
}
