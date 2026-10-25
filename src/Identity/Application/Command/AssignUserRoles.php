<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Command;

use Watchdog\Identity\Application\Service\Actor;
use Watchdog\Identity\Application\Service\RoleResolver;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Identity\Domain\User\Exception\LastSuperAdmin;
use Watchdog\Identity\Domain\User\Exception\PrivilegeEscalation;
use Watchdog\Identity\Domain\User\Exception\UserNotFound;
use Watchdog\Identity\Domain\User\UserId;
use Watchdog\Identity\Domain\User\UserRepository;

final readonly class AssignUserRoles
{
    public function __construct(
        private UserRepository $users,
        private RoleResolver $roles,
        private Actor $actor,
    ) {
    }

    /**
     * @param list<string> $roleCodes
     */
    public function __invoke(
        UserId $actorId,
        UserId $userId,
        array $roleCodes,
    ): void {
        $user = $this->users->ofId($userId)
            ?? throw UserNotFound::withId($userId);
        $actor = $this->actor->load($actorId);

        $roles = $this->roles->byCodes($roleCodes);
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
