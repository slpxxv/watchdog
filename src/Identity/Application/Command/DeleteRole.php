<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Command;

use Watchdog\Identity\Domain\Role\Exception\CannotDeleteSystemRole;
use Watchdog\Identity\Domain\Role\Exception\RoleNotFound;
use Watchdog\Identity\Domain\Role\RoleId;
use Watchdog\Identity\Domain\Role\RoleRepository;

final readonly class DeleteRole
{
    public function __construct(private RoleRepository $roles)
    {
    }

    /**
     * Assignments are removed by the ON DELETE CASCADE foreign key.
     */
    public function __invoke(RoleId $id): void
    {
        $role = $this->roles->ofId($id) ?? throw RoleNotFound::withCode($id->value);

        if ($role->isSystem()) {
            throw CannotDeleteSystemRole::code($role->code());
        }

        $this->roles->remove($role);
    }
}
