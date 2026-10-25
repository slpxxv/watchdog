<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Identity\Application;

use Watchdog\Identity\Application\Command\CreateRole;
use Watchdog\Identity\Application\Command\DeleteRole;
use Watchdog\Identity\Application\Command\UpdateRole;
use Watchdog\Identity\Domain\Role\Exception\CannotDeleteSystemRole;
use Watchdog\Identity\Domain\Role\Exception\InvalidRoleCode;
use Watchdog\Identity\Domain\Role\Exception\RoleAlreadyExists;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Identity\Domain\User\Exception\PrivilegeEscalation;
use Watchdog\Shared\Domain\Permission;

final class RoleManagementTest extends AclTestCase
{
    public function testSuperAdminCreatesRole(): void
    {
        $admin = $this->user('admin@example.com', [Role::SUPER_ADMIN]);
        $id = (new CreateRole($this->roles, $this->actor))($admin, 'editor', 'Editor', [Permission::RoleView]);

        $role = $this->roles->ofId($id);
        self::assertNotNull($role);
        self::assertTrue($role->grants(Permission::RoleView));
        self::assertFalse($role->grants(Permission::RoleManage));
    }

    public function testDuplicateCodeIsRejected(): void
    {
        $admin = $this->user('admin@example.com', [Role::SUPER_ADMIN]);

        $this->expectException(RoleAlreadyExists::class);
        (new CreateRole($this->roles, $this->actor))($admin, Role::USER, 'Dup', []);
    }

    public function testInvalidCodeIsRejected(): void
    {
        $this->expectException(InvalidRoleCode::class);
        Role::create($this->roles->nextIdentity(), 'Bad Code!', 'x', []);
    }

    public function testCannotGrantPermissionYouDoNotHold(): void
    {
        $this->role('role_admin', [Permission::RoleView, Permission::RoleManage]);
        $actor = $this->user('ra@example.com', ['role_admin']);

        $this->expectException(PrivilegeEscalation::class);
        (new CreateRole($this->roles, $this->actor))($actor, 'sneaky', 'Sneaky', [Permission::UserManage]);
    }

    public function testCannotAddPermissionToExistingRoleYouDoNotHold(): void
    {
        $this->role('role_admin', [Permission::RoleView, Permission::RoleManage]);
        $actor = $this->user('ra@example.com', ['role_admin']);
        $target = $this->role('target', [Permission::UserView]);

        // Keeping a permission the actor lacks is fine; adding one is not.
        $handler = new UpdateRole($this->roles, $this->actor);
        $handler($actor, $target->id(), 'Target', [Permission::UserView, Permission::RoleView]);
        self::assertSame([Permission::UserView, Permission::RoleView], $target->permissions());

        $this->expectException(PrivilegeEscalation::class);
        $handler($actor, $target->id(), 'Target', [Permission::UserView, Permission::UserManage]);
    }

    public function testSystemRoleCannotBeDeleted(): void
    {
        $role = $this->roles->ofCode(Role::USER);
        self::assertNotNull($role);

        $this->expectException(CannotDeleteSystemRole::class);
        (new DeleteRole($this->roles))($role->id());
    }

    public function testCustomRoleCanBeDeleted(): void
    {
        $role = $this->role('temp', []);
        (new DeleteRole($this->roles))($role->id());

        self::assertNull($this->roles->ofCode('temp'));
    }
}
