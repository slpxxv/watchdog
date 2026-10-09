<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Identity\Domain;

use PHPUnit\Framework\TestCase;
use Watchdog\Identity\Domain\User\Exception\PrivilegeEscalation;
use Watchdog\Shared\Domain\Permission;
use Watchdog\Tests\Double\Identities;

final class UserTest extends TestCase
{
    public function testAssignRolesReplacesAndDeduplicates(): void
    {
        $viewer = Identities::role('viewer', [Permission::RoleView]);
        $user = Identities::user(roles: [Identities::role('auditor', [Permission::UserView])]);

        $user->assignRoles([$viewer, $viewer]);

        self::assertSame([$viewer], $user->roles());
        self::assertFalse($user->can(Permission::UserView));
    }

    public function testPermissionsAreTheUnionOfRolesInCatalogueOrder(): void
    {
        $user = Identities::user(roles: [
            Identities::role('role_admin', [Permission::RoleManage]),
            Identities::role('auditor', [Permission::UserView]),
        ]);

        self::assertSame([Permission::UserView, Permission::RoleManage], $user->permissions());
        self::assertFalse($user->isSuperAdmin());
    }

    public function testUserWithoutRolesCanDoNothing(): void
    {
        self::assertSame([], Identities::user()->permissions());
    }

    public function testSuperAdminHoldsEveryPermission(): void
    {
        $user = Identities::user(roles: [Identities::superAdmin()]);

        self::assertTrue($user->isSuperAdmin());
        self::assertSame(Permission::cases(), $user->permissions());
    }

    public function testItCanGrantOnlyPermissionsItHolds(): void
    {
        $user = Identities::user(roles: [Identities::role('viewer', [Permission::RoleView])]);
        $user->assertCanGrant([Permission::RoleView]);

        $this->expectException(PrivilegeEscalation::class);
        $user->assertCanGrant([Permission::RoleView, Permission::RoleManage]);
    }

    public function testChangePasswordHash(): void
    {
        $user = Identities::user(passwordHash: 'old');
        $user->changePasswordHash('new');

        self::assertSame('new', $user->passwordHash());
    }
}
