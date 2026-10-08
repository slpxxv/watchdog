<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\Command\AssignUserRoles\AssignUserRoles;
use App\Identity\Application\Command\AssignUserRoles\AssignUserRolesHandler;
use App\Identity\Application\Service\RoleResolver;
use App\Identity\Domain\Role\Role;
use App\Identity\Domain\User\Exception\LastSuperAdmin;
use App\Identity\Domain\User\Exception\PrivilegeEscalation;
use App\Identity\Domain\User\UserId;
use App\Shared\Domain\Permission;

final class AssignUserRolesHandlerTest extends AclTestCase
{
    private AssignUserRolesHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new AssignUserRolesHandler($this->users, new RoleResolver($this->roles), $this->actor);
    }

    /**
     * @param list<string> $codes
     */
    private function assign(UserId $actor, UserId $target, array $codes): void
    {
        ($this->handler)(new AssignUserRoles($actor, $target, $codes));
    }

    public function testPermissionsFollowAssignedRoles(): void
    {
        $this->role('viewer', [Permission::UserView]);
        $admin = $this->user('admin@example.com', [Role::SUPER_ADMIN]);
        $bob = $this->user('bob@example.com');

        $this->assign($admin, $bob, [Role::USER, 'viewer']);

        $user = $this->users->ofId($bob);
        self::assertNotNull($user);
        self::assertTrue($user->can(Permission::UserView));
        self::assertFalse($user->can(Permission::UserManage));
    }

    public function testLastSuperAdminCannotBeDemoted(): void
    {
        $admin = $this->user('admin@example.com', [Role::SUPER_ADMIN]);

        try {
            $this->assign($admin, $admin, [Role::USER]);
            self::fail('Expected LastSuperAdmin.');
        } catch (LastSuperAdmin) {
        }

        // The rejected change must not leak into the aggregate.
        self::assertTrue($this->users->ofId($admin)?->isSuperAdmin());
    }

    public function testSuperAdminCanBeDemotedWhenAnotherRemains(): void
    {
        $a = $this->user('a@example.com', [Role::SUPER_ADMIN]);
        $b = $this->user('b@example.com', [Role::SUPER_ADMIN]);

        $this->assign($a, $b, [Role::USER]);

        self::assertFalse($this->users->ofId($b)?->isSuperAdmin());
    }

    public function testOnlySuperAdminCanPromoteToSuperAdmin(): void
    {
        $this->role('user_admin', [Permission::UserView, Permission::UserManage]);
        $this->user('root@example.com', [Role::SUPER_ADMIN]);
        $manager = $this->user('manager@example.com', ['user_admin']);

        $this->expectException(PrivilegeEscalation::class);
        $this->assign($manager, $manager, ['user_admin', Role::SUPER_ADMIN]);
    }

    public function testNonSuperAdminCannotTouchSuperAdmin(): void
    {
        $this->role('user_admin', [Permission::UserView, Permission::UserManage]);
        $root = $this->user('root@example.com', [Role::SUPER_ADMIN]);
        $manager = $this->user('manager@example.com', ['user_admin']);

        $this->expectException(PrivilegeEscalation::class);
        $this->assign($manager, $root, [Role::USER]);
    }

    public function testCannotAssignRoleWithPermissionsYouLack(): void
    {
        $this->role('user_admin', [Permission::UserView, Permission::UserManage]);
        $this->role('role_admin', [Permission::RoleManage]);
        $manager = $this->user('manager@example.com', ['user_admin']);
        $bob = $this->user('bob@example.com');

        $this->expectException(PrivilegeEscalation::class);
        $this->assign($manager, $bob, ['role_admin']);
    }
}
