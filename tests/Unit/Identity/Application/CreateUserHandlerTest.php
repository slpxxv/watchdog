<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\Command\CreateUser\CreateUser;
use App\Identity\Application\Exception\WeakPassword;
use App\Identity\Domain\Acl\Permission;
use App\Identity\Domain\Role\Exception\RoleNotFound;
use App\Identity\Domain\Role\Role;
use App\Identity\Domain\User\Exception\UserAlreadyExists;

final class CreateUserHandlerTest extends AclTestCase
{
    public function testItCreatesUserWithHashedPasswordAndRoles(): void
    {
        $user = $this->users->ofId($this->user('Admin@Example.com', [Role::USER, Role::SUPER_ADMIN]));

        self::assertNotNull($user);
        self::assertSame('admin@example.com', $user->email()->value);
        self::assertSame('hashed:correct horse battery', $user->passwordHash());
        self::assertTrue($user->isSuperAdmin());
        self::assertSame(Permission::cases(), $user->permissions());
    }

    public function testItRejectsDuplicateEmail(): void
    {
        $this->user('a@example.com');

        $this->expectException(UserAlreadyExists::class);
        $this->user('A@example.com');
    }

    public function testItRejectsShortPassword(): void
    {
        $this->expectException(WeakPassword::class);
        ($this->createUser)(new CreateUser('a@example.com', 'short'));
    }

    public function testItRejectsUnknownRole(): void
    {
        $this->expectException(RoleNotFound::class);
        $this->user('a@example.com', ['nope']);
    }
}
