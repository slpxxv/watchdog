<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\Permission;
use App\Tests\Double\Identities;
use PHPUnit\Framework\TestCase;

final class SecurityUserTest extends TestCase
{
    public function testItMirrorsTheDomainUser(): void
    {
        $user = Identities::user('jane@example.com', [Identities::role('viewer', [Permission::RoleView])], 'secret-hash');

        $securityUser = SecurityUser::fromUser($user);

        self::assertSame($user->id()->value, $securityUser->id);
        self::assertSame('jane@example.com', $securityUser->getUserIdentifier());
        self::assertSame('secret-hash', $securityUser->getPassword());
        self::assertSame(['ROLE_USER'], $securityUser->getRoles());
        self::assertTrue($securityUser->can(Permission::RoleView));
        self::assertFalse($securityUser->can(Permission::RoleManage));
    }

    public function testSuperAdminGetsTheCoarseRoleAndEveryPermission(): void
    {
        $securityUser = SecurityUser::fromUser(Identities::user(roles: [Identities::superAdmin()]));

        self::assertSame(['ROLE_USER', SecurityUser::ROLE_SUPER_ADMIN], $securityUser->getRoles());
        foreach (Permission::cases() as $permission) {
            self::assertTrue($securityUser->can($permission));
        }
    }

    public function testPasswordHashNeverReachesTheSession(): void
    {
        $securityUser = SecurityUser::fromUser(Identities::user('jane@example.com', [Identities::role('viewer', [Permission::RoleView])], 'secret-hash'));

        $serialized = serialize($securityUser);
        $restored = unserialize($serialized);

        self::assertStringNotContainsString('secret-hash', $serialized);
        self::assertInstanceOf(SecurityUser::class, $restored);
        self::assertNull($restored->getPassword());
        self::assertSame('jane@example.com', $restored->getUserIdentifier());
        self::assertTrue($restored->can(Permission::RoleView));
    }
}
