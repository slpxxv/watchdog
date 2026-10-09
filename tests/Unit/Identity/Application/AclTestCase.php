<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Identity\Application;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Watchdog\Identity\Application\Command\CreateUser\CreateUser;
use Watchdog\Identity\Application\Command\CreateUser\CreateUserHandler;
use Watchdog\Identity\Application\Port\PasswordHasher;
use Watchdog\Identity\Application\Service\Actor;
use Watchdog\Identity\Application\Service\RoleResolver;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Identity\Domain\User\UserId;
use Watchdog\Shared\Domain\Permission;
use Watchdog\Tests\Double\InMemoryRoleRepository;
use Watchdog\Tests\Double\InMemoryUserRepository;

abstract class AclTestCase extends TestCase
{
    protected InMemoryUserRepository $users;
    protected InMemoryRoleRepository $roles;
    protected Actor $actor;
    protected CreateUserHandler $createUser;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->roles = InMemoryRoleRepository::withSystemRoles();
        $this->actor = new Actor($this->users);
        $this->createUser = new CreateUserHandler(
            $this->users,
            new RoleResolver($this->roles),
            new class implements PasswordHasher {
                public function hash(string $plainPassword): string
                {
                    return 'hashed:'.$plainPassword;
                }
            },
            new MockClock('2026-01-01'),
        );
    }

    /**
     * @param list<string> $roles
     */
    protected function user(string $email, array $roles = [Role::USER]): UserId
    {
        return ($this->createUser)(new CreateUser($email, 'correct horse battery', $roles));
    }

    /**
     * @param list<Permission> $permissions
     */
    protected function role(string $code, array $permissions): Role
    {
        $role = Role::create($this->roles->nextIdentity(), $code, ucfirst($code), $permissions);
        $this->roles->save($role);

        return $role;
    }
}
