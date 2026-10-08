<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\AssignUserRoles;

use App\Identity\Application\Service\Actor;
use App\Identity\Application\Service\RoleResolver;
use App\Identity\Domain\Acl\Exception\PrivilegeEscalation;
use App\Identity\Domain\Role\Role;
use App\Identity\Domain\User\Exception\LastSuperAdmin;
use App\Identity\Domain\User\Exception\UserNotFound;
use App\Identity\Domain\User\UserRepository;

final readonly class AssignUserRolesHandler
{
    public function __construct(
        private UserRepository $users,
        private RoleResolver $roles,
        private Actor $actor,
    ) {
    }

    public function __invoke(AssignUserRoles $command): void
    {
        $user = $this->users->ofId($command->userId)
            ?? throw UserNotFound::withId($command->userId);
        $actor = $this->actor->load($command->actorId);

        $roles = $this->roles->byCodes($command->roleCodes);
        $added = array_filter($roles, static fn (Role $r): bool => !\in_array($r, $user->roles(), true));
        $staysSuperAdmin = array_any($roles, static fn (Role $r): bool => $r->isSuperAdmin());

        if (!$actor->isSuperAdmin() && ($user->isSuperAdmin() || $staysSuperAdmin)) {
            throw PrivilegeEscalation::superAdminOnly();
        }
        foreach ($added as $role) {
            $actor->assertCanGrant($role->permissions());
        }

        // Checked before mutating, so a rejected change never leaves a dirty aggregate behind.
        // ponytail: check-then-act; two admins demoting each other at the same instant could both pass. Add a DB lock if that matters.
        if ($user->isSuperAdmin() && !$staysSuperAdmin && $this->users->countSuperAdmins() <= 1) {
            throw LastSuperAdmin::create();
        }

        $user->assignRoles($roles);
        $this->users->save($user);
    }
}
