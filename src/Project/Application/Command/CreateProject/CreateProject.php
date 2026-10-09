<?php

declare(strict_types=1);

namespace Watchdog\Project\Application\Command\CreateProject;

final readonly class CreateProject
{
    public function __construct(
        public string $name,
    ) {
    }
}
