<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Command;

use Watchdog\Identity\Application\Service\Actor;
use Watchdog\Identity\Domain\Role\Exception\RoleNotFound;
use Watchdog\Identity\Domain\Role\RoleId;
use Watchdog\Identity\Domain\Role\RoleRepository;
use Watchdog\Identity\Domain\User\Exception\PrivilegeEscalation;
use Watchdog\Identity\Domain\User\UserId;
use Watchdog\Shared\Domain\Permission;

final readonly class UpdateRole
{
    public function __construct(
        private RoleRepository $roles,
        private Actor $actor,
    ) {
    }

    /**
     * @param list<Permission> $permissions
     */
    public function __invoke(
        UserId $actorId,
        RoleId $id,
        string $name,
        array $permissions,
    ): void {
        $role = $this->roles->ofId($id) ?? throw RoleNotFound::withCode($id->value);
        $actor = $this->actor->load($actorId);

        if ($role->isSuperAdmin()) {
            if (!$actor->isSuperAdmin()) {
                throw PrivilegeEscalation::superAdminOnly();
            }
        } else {
            // Only newly added permissions need checking; removing is always allowed.
            $actor->assertCanGrant(array_values(array_udiff(
                $permissions,
                $role->permissions(),
                static fn (Permission $a, Permission $b): int => strcmp($a->value, $b->value),
            )));
            $role->grant($permissions);
        }

        $role->rename($name);
        $this->roles->save($role);
    }
}
