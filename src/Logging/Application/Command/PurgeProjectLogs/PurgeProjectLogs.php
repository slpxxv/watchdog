<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command\PurgeProjectLogs;

/**
 * Sent after a project is deleted; handled asynchronously because a project can hold millions of lines.
 */
final readonly class PurgeProjectLogs
{
    public function __construct(
        public string $projectId,
    ) {
    }
}
