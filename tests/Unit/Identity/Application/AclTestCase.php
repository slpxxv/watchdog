<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\Command\CreateUser\CreateUser;
use App\Identity\Application\Command\CreateUser\CreateUserHandler;
use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Application\Service\Actor;
use App\Identity\Application\Service\RoleResolver;
use App\Identity\Domain\Acl\Permission;
use App\Identity\Domain\Role\Role;
use App\Identity\Domain\User\UserId;
use App\Tests\Double\InMemoryRoleRepository;
use App\Tests\Double\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

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
