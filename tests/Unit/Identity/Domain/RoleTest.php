<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Role\Role;
use App\Identity\Domain\Role\RoleId;
use App\Shared\Domain\Permission;
use App\Tests\Double\InMemoryRoleRepository;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function testGrantReplacesAndDeduplicates(): void
    {
        $role = Role::create(RoleId::fromString('00000000-0000-7000-8000-000000000001'), 'editor', 'Editor', [Permission::UserView]);
        $role->grant([Permission::RoleView, Permission::RoleView]);

        self::assertSame([Permission::RoleView], $role->permissions());
        self::assertFalse($role->grants(Permission::UserView));
    }

    public function testSuperAdminGrantsEverything(): void
    {
        $superAdmin = InMemoryRoleRepository::withSystemRoles()->ofCode(Role::SUPER_ADMIN);
        self::assertNotNull($superAdmin);

        foreach (Permission::cases() as $permission) {
            self::assertTrue($superAdmin->grants($permission));
        }
    }
}
