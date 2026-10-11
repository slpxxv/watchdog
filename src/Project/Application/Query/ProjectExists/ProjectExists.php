<?php

declare(strict_types=1);

namespace Watchdog\Project\Application\Query\ProjectExists;

final readonly class ProjectExists
{
    public function __construct(
        public string $id,
    ) {
    }
}
