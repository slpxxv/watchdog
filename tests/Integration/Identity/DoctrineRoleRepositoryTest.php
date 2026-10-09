<?php

declare(strict_types=1);

namespace Watchdog\Tests\Integration\Identity;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Identity\Domain\Role\RoleRepository;
use Watchdog\Identity\Domain\User\Email;
use Watchdog\Identity\Domain\User\User;
use Watchdog\Identity\Domain\User\UserRepository;
use Watchdog\Shared\Domain\Permission;

final class DoctrineRoleRepositoryTest extends KernelTestCase
{
    private RoleRepository $roles;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->roles = self::getContainer()->get(RoleRepository::class);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param list<Permission> $permissions
     */
    private function role(string $code, string $name, array $permissions = []): Role
    {
        $role = Role::create($this->roles->nextIdentity(), $code, $name, $permissions);
        $this->roles->save($role);

        return $role;
    }

    public function testMigrationSeedsSystemRoles(): void
    {
        $superAdmin = $this->roles->ofCode(Role::SUPER_ADMIN);
        $user = $this->roles->ofCode(Role::USER);

        self::assertNotNull($superAdmin);
        self::assertTrue($superAdmin->isSuperAdmin());
        self::assertTrue($superAdmin->isSystem());
        self::assertNotNull($user);
        self::assertFalse($user->isSuperAdmin());
        self::assertTrue($user->isSystem());
    }

    public function testSavedRoleIsHydratedFromDatabase(): void
    {
        $id = $this->role('editor', 'Editor', [Permission::RoleView, Permission::UserView])->id();
        $this->em->clear();

        $role = $this->roles->ofId($id);

        self::assertNotNull($role);
        self::assertSame('editor', $role->code());
        self::assertSame('Editor', $role->name());
        self::assertSame([Permission::UserView, Permission::RoleView], $role->permissions());
        self::assertFalse($role->isSystem());
    }

    public function testUnknownRoleReturnsNull(): void
    {
        self::assertNull($this->roles->ofId($this->roles->nextIdentity()));
        self::assertNull($this->roles->ofCode('nope'));
    }

    public function testAllListsSystemRolesFirstThenByName(): void
    {
        $this->role('zeta', 'Zeta');
        $this->role('alpha', 'Alpha');
        $this->em->clear();

        $codes = array_map(static fn (Role $r): string => $r->code(), $this->roles->all());

        self::assertSame([Role::SUPER_ADMIN, Role::USER, 'alpha', 'zeta'], $codes);
    }

    public function testRemovingRoleDropsItsAssignments(): void
    {
        $users = self::getContainer()->get(UserRepository::class);
        $editor = $this->role('editor', 'Editor');
        $user = User::register($users->nextIdentity(), Email::fromString('jane@example.com'), 'hash', [$editor], new \DateTimeImmutable());
        $users->save($user);

        $this->roles->remove($editor);
        $this->em->clear();

        self::assertNull($this->roles->ofCode('editor'));
        self::assertSame([], $users->ofId($user->id())?->roles());
    }
}
