<?php

declare(strict_types=1);

namespace Watchdog\Project\Application\Command\DeleteProject;

final readonly class DeleteProject
{
    public function __construct(
        public string $id,
    ) {
    }
}
