<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\Command\CreateRole\CreateRole;
use App\Identity\Application\Command\CreateRole\CreateRoleHandler;
use App\Identity\Application\Command\DeleteRole\DeleteRole;
use App\Identity\Application\Command\DeleteRole\DeleteRoleHandler;
use App\Identity\Application\Command\UpdateRole\UpdateRole;
use App\Identity\Application\Command\UpdateRole\UpdateRoleHandler;
use App\Identity\Domain\Role\Exception\CannotDeleteSystemRole;
use App\Identity\Domain\Role\Exception\InvalidRoleCode;
use App\Identity\Domain\Role\Exception\RoleAlreadyExists;
use App\Identity\Domain\Role\Role;
use App\Identity\Domain\User\Exception\PrivilegeEscalation;
use App\Shared\Domain\Permission;

final class RoleManagementTest extends AclTestCase
{
    public function testSuperAdminCreatesRole(): void
    {
        $admin = $this->user('admin@example.com', [Role::SUPER_ADMIN]);
        $id = (new CreateRoleHandler($this->roles, $this->actor))(new CreateRole($admin, 'editor', 'Editor', [Permission::RoleView]));

        $role = $this->roles->ofId($id);
        self::assertNotNull($role);
        self::assertTrue($role->grants(Permission::RoleView));
        self::assertFalse($role->grants(Permission::RoleManage));
    }

    public function testDuplicateCodeIsRejected(): void
    {
        $admin = $this->user('admin@example.com', [Role::SUPER_ADMIN]);

        $this->expectException(RoleAlreadyExists::class);
        (new CreateRoleHandler($this->roles, $this->actor))(new CreateRole($admin, Role::USER, 'Dup', []));
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
        (new CreateRoleHandler($this->roles, $this->actor))(new CreateRole($actor, 'sneaky', 'Sneaky', [Permission::UserManage]));
    }

    public function testCannotAddPermissionToExistingRoleYouDoNotHold(): void
    {
        $this->role('role_admin', [Permission::RoleView, Permission::RoleManage]);
        $actor = $this->user('ra@example.com', ['role_admin']);
        $target = $this->role('target', [Permission::UserView]);

        // Keeping a permission the actor lacks is fine; adding one is not.
        $handler = new UpdateRoleHandler($this->roles, $this->actor);
        $handler(new UpdateRole($actor, $target->id(), 'Target', [Permission::UserView, Permission::RoleView]));
        self::assertSame([Permission::UserView, Permission::RoleView], $target->permissions());

        $this->expectException(PrivilegeEscalation::class);
        $handler(new UpdateRole($actor, $target->id(), 'Target', [Permission::UserView, Permission::UserManage]));
    }

    public function testSystemRoleCannotBeDeleted(): void
    {
        $role = $this->roles->ofCode(Role::USER);
        self::assertNotNull($role);

        $this->expectException(CannotDeleteSystemRole::class);
        (new DeleteRoleHandler($this->roles))(new DeleteRole($role->id()));
    }

    public function testCustomRoleCanBeDeleted(): void
    {
        $role = $this->role('temp', []);
        (new DeleteRoleHandler($this->roles))(new DeleteRole($role->id()));

        self::assertNull($this->roles->ofCode('temp'));
    }
}
