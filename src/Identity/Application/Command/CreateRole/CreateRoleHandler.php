<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\CreateRole;

use App\Identity\Application\Service\Actor;
use App\Identity\Domain\Role\Exception\RoleAlreadyExists;
use App\Identity\Domain\Role\Role;
use App\Identity\Domain\Role\RoleId;
use App\Identity\Domain\Role\RoleRepository;

final readonly class CreateRoleHandler
{
    public function __construct(
        private RoleRepository $roles,
        private Actor $actor,
    ) {
    }

    public function __invoke(CreateRole $command): RoleId
    {
        $this->actor->load($command->actorId)->assertCanGrant($command->permissions);

        if (null !== $this->roles->ofCode($command->code)) {
            throw RoleAlreadyExists::withCode($command->code);
        }

        $role = Role::create(
            id: $this->roles->nextIdentity(),
            code: $command->code,
            name: $command->name,
            permissions: $command->permissions,
        );
        $this->roles->save($role);

        return $role->id();
    }
}
