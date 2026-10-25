<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Identity\Application;

use Watchdog\Identity\Application\Query\GetUser;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Identity\Domain\User\Exception\UserNotFound;
use Watchdog\Identity\Domain\User\UserId;
use Watchdog\Shared\Domain\Permission;

final class GetUserTest extends AclTestCase
{
    public function testItMapsUserToDto(): void
    {
        $id = $this->user('Jane@Example.com');
        $this->users->ofId($id)?->assignRoles([$this->role('viewer', [Permission::RoleView, Permission::UserView])]);

        $dto = (new GetUser($this->users))($id);

        self::assertSame($id->value, $dto->id);
        self::assertSame('jane@example.com', $dto->email);
        self::assertSame(['viewer'], $dto->roles);
        self::assertSame(['user.view', 'role.view'], $dto->permissions, 'catalogue order');
        self::assertStringStartsWith('2026-01-01T00:00:00', $dto->createdAt, 'ISO 8601 (MockClock in AclTestCase)');
    }

    public function testSuperAdminGetsEveryPermission(): void
    {
        $dto = (new GetUser($this->users))($this->user('root@example.com', [Role::SUPER_ADMIN]));

        self::assertSame([Role::SUPER_ADMIN], $dto->roles);
        self::assertSame(array_column(Permission::cases(), 'value'), $dto->permissions);
    }

    public function testItRejectsUnknownUser(): void
    {
        $this->expectException(UserNotFound::class);
        (new GetUser($this->users))(UserId::fromString('01a11c9a-0c58-715b-a7c3-cadec5d3f36a'));
    }
}
