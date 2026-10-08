<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\DeleteRole;

use App\Identity\Domain\Role\Exception\CannotDeleteSystemRole;
use App\Identity\Domain\Role\Exception\RoleNotFound;
use App\Identity\Domain\Role\RoleRepository;

final readonly class DeleteRoleHandler
{
    public function __construct(private RoleRepository $roles)
    {
    }

    /**
     * Assignments are removed by the ON DELETE CASCADE foreign key.
     */
    public function __invoke(DeleteRole $command): void
    {
        $role = $this->roles->ofId($command->id) ?? throw RoleNotFound::withCode($command->id->value);

        if ($role->isSystem()) {
            throw CannotDeleteSystemRole::code($role->code());
        }

        $this->roles->remove($role);
    }
}
