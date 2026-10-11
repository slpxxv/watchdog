<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Query\ListSources;

final readonly class ListSources
{
    public function __construct(
        public string $projectId,
    ) {
    }
}
