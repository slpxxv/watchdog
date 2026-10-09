<?php

declare(strict_types=1);

namespace Watchdog\Project\Application\Command\RenameProject;

final readonly class RenameProject
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
