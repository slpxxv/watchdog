<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Identity\Domain;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Watchdog\Identity\Domain\Role\Exception\InvalidRoleCode;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Identity\Domain\Role\RoleId;
use Watchdog\Shared\Domain\Permission;
use Watchdog\Tests\Double\InMemoryRoleRepository;

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

    public function testPermissionsFollowCatalogueOrder(): void
    {
        $role = Role::create(RoleId::fromString('00000000-0000-7000-8000-000000000001'), 'editor', 'Editor', [Permission::RoleManage, Permission::UserView]);

        self::assertSame([Permission::UserView, Permission::RoleManage], $role->permissions());
    }

    public function testRenameTrims(): void
    {
        $role = Role::create(RoleId::fromString('00000000-0000-7000-8000-000000000001'), 'editor', '  Editor  ', []);

        self::assertSame('Editor', $role->name());
    }

    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith(['x', 101])]
    public function testRenameRejectsBlankOrTooLong(string $char, int $times = 1): void
    {
        $role = Role::create(RoleId::fromString('00000000-0000-7000-8000-000000000001'), 'editor', 'Editor', []);

        $this->expectException(\InvalidArgumentException::class);
        $role->rename(str_repeat($char, $times));
    }

    #[TestWith(['Editor'])]
    #[TestWith(['e'])]
    #[TestWith(['1editor'])]
    #[TestWith(['edi-tor'])]
    public function testItRejectsInvalidCode(string $code): void
    {
        $this->expectException(InvalidRoleCode::class);
        Role::create(RoleId::fromString('00000000-0000-7000-8000-000000000001'), $code, 'Editor', []);
    }
}
