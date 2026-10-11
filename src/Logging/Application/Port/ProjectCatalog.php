<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Port;

use Watchdog\Logging\Domain\ProjectId;

interface ProjectCatalog
{
    public function exists(ProjectId $id): bool;
}
