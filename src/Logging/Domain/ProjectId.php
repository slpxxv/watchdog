<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain;

use Watchdog\Shared\Domain\Uuid;

/**
 * Reference to a project owned by the Project context; Logging keeps only the id.
 */
final readonly class ProjectId extends Uuid
{
}
