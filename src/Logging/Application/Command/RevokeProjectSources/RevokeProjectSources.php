<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command\RevokeProjectSources;

final readonly class RevokeProjectSources
{
    public function __construct(
        public string $projectId,
    ) {
    }
}
