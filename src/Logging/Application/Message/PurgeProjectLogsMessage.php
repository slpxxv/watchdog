<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Message;

/**
 * Sent after a project is deleted; handled asynchronously because a project can hold millions of lines.
 */
final readonly class PurgeProjectLogsMessage
{
    public function __construct(
        public string $projectId,
    ) {
    }
}
