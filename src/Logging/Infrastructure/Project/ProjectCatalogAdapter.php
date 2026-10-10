<?php

declare(strict_types=1);

namespace Watchdog\Logging\Infrastructure\Project;

use Watchdog\Logging\Application\Port\ProjectCatalog;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Project\Application\Query\ProjectExists\ProjectExists;
use Watchdog\Project\Application\Query\ProjectExists\ProjectExistsHandler;

/**
 * Asks the Project context through its public query, never its repository.
 */
final readonly class ProjectCatalogAdapter implements ProjectCatalog
{
    public function __construct(
        private ProjectExistsHandler $projectExists,
    ) {
    }

    public function exists(ProjectId $id): bool
    {
        return ($this->projectExists)(new ProjectExists($id->value));
    }
}
