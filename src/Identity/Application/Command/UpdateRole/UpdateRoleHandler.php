<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Command\UpdateRole;

use Watchdog\Identity\Application\Service\Actor;
use Watchdog\Identity\Domain\Role\Exception\RoleNotFound;
use Watchdog\Identity\Domain\Role\RoleRepository;
use Watchdog\Identity\Domain\User\Exception\PrivilegeEscalation;
use Watchdog\Shared\Domain\Permission;

final readonly class UpdateRoleHandler
{
    public function __construct(
        private RoleRepository $roles,
        private Actor $actor,
    ) {
    }

    public function __invoke(UpdateRole $command): void
    {
        $role = $this->roles->ofId($command->id) ?? throw RoleNotFound::withCode($command->id->value);
        $actor = $this->actor->load($command->actorId);

        if ($role->isSuperAdmin()) {
            if (!$actor->isSuperAdmin()) {
                throw PrivilegeEscalation::superAdminOnly();
            }
        } else {
            // Only newly added permissions need checking; removing is always allowed.
            $actor->assertCanGrant(array_values(array_udiff(
                $command->permissions,
                $role->permissions(),
                static fn (Permission $a, Permission $b): int => strcmp($a->value, $b->value),
            )));
            $role->grant($command->permissions);
        }

        $role->rename($command->name);
        $this->roles->save($role);
    }
}
