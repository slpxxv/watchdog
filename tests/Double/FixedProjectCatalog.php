<?php

declare(strict_types=1);

namespace Watchdog\Tests\Double;

use Watchdog\Logging\Application\Port\ProjectCatalog;
use Watchdog\Logging\Domain\ProjectId;

final readonly class FixedProjectCatalog implements ProjectCatalog
{
    /**
     * @param list<string> $existing project ids
     */
    public function __construct(
        private array $existing,
    ) {
    }

    public function exists(ProjectId $id): bool
    {
        return \in_array($id->value, $this->existing, true);
    }
}
