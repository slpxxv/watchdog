<?php

declare(strict_types=1);

namespace Watchdog\Project\Application\Event;

/**
 * Published after a project is removed, so other contexts can clean up what they keep for it.
 */
final readonly class ProjectDeleted
{
    public function __construct(
        public string $projectId,
    ) {
    }
}
