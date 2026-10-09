<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Command\DeleteRole;

use Watchdog\Identity\Domain\Role\RoleId;

final readonly class DeleteRole
{
    public function __construct(public RoleId $id)
    {
    }
}
