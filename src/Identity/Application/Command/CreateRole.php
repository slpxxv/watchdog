<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Command;

use Watchdog\Identity\Application\Service\Actor;
use Watchdog\Identity\Domain\Role\Exception\RoleAlreadyExists;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Identity\Domain\Role\RoleId;
use Watchdog\Identity\Domain\Role\RoleRepository;
use Watchdog\Identity\Domain\User\UserId;
use Watchdog\Shared\Domain\Permission;

final readonly class CreateRole
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
        string $code,
        string $name,
        array $permissions,
    ): RoleId {
        $this->actor->load($actorId)->assertCanGrant($permissions);

        if (null !== $this->roles->ofCode($code)) {
            throw RoleAlreadyExists::withCode($code);
        }

        $role = Role::create(
            id: $this->roles->nextIdentity(),
            code: $code,
            name: $name,
            permissions: $permissions,
        );
        $this->roles->save($role);

        return $role->id();
    }
}
