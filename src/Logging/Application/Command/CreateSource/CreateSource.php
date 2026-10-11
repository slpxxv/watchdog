<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command\CreateSource;

final readonly class CreateSource
{
    public function __construct(
        public string $projectId,
        public string $name,
    ) {
    }
}
