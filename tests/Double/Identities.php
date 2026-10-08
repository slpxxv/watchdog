<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Identity\Domain\Role\Role;
use App\Identity\Domain\Role\RoleId;
use App\Identity\Domain\User\Email;
use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserId;
use App\Shared\Domain\Permission;

/**
 * Builds domain users and roles for tests that don't go through the application handlers.
 */
final class Identities
{
    private static int $sequence = 0;

    /**
     * @param list<Role> $roles
     */
    public static function user(string $email = 'jane@example.com', array $roles = [], string $passwordHash = 'hash'): User
    {
        return User::register(
            UserId::fromString(\sprintf('00000000-0000-7000-a000-%012d', ++self::$sequence)),
            Email::fromString($email),
            $passwordHash,
            $roles,
            new \DateTimeImmutable('2026-01-01'),
        );
    }

    /**
     * @param list<Permission> $permissions
     */
    public static function role(string $code, array $permissions): Role
    {
        return Role::create(RoleId::fromString(\sprintf('00000000-0000-7000-b000-%012d', ++self::$sequence)), $code, ucfirst($code), $permissions);
    }

    public static function superAdmin(): Role
    {
        return InMemoryRoleRepository::withSystemRoles()->ofCode(Role::SUPER_ADMIN) ?? throw new \LogicException('Seed is missing super_admin.');
    }
}
