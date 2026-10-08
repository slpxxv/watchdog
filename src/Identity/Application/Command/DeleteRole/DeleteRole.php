<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\DeleteRole;

use App\Identity\Domain\Role\RoleId;

final readonly class DeleteRole
{
    public function __construct(public RoleId $id)
    {
    }
}
