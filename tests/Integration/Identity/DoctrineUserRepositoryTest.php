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

final class DoctrineUserRepositoryTest extends KernelTestCase
{
    private UserRepository $users;
    private RoleRepository $roles;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->users = self::getContainer()->get(UserRepository::class);
        $this->roles = self::getContainer()->get(RoleRepository::class);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param list<string> $roleCodes
     */
    private function user(string $email, array $roleCodes = [Role::USER]): User
    {
        $roles = array_map(fn (string $code): Role => $this->roles->ofCode($code) ?? self::fail("No role {$code}."), $roleCodes);
        $user = User::register($this->users->nextIdentity(), Email::fromString($email), 'hash', $roles, new \DateTimeImmutable('2026-01-01 12:00:00'));
        $this->users->save($user);

        return $user;
    }

    /**
     * @param list<User> $users
     *
     * @return list<string>
     */
    private function emails(array $users): array
    {
        return array_map(static fn (User $u): string => $u->email()->value, $users);
    }

    public function testSavedUserIsHydratedWithRoles(): void
    {
        $id = $this->user('jane@example.com', [Role::USER, Role::SUPER_ADMIN])->id();
        $this->em->clear();

        $user = $this->users->ofId($id);

        self::assertNotNull($user);
        self::assertSame('jane@example.com', $user->email()->value);
        self::assertSame('hash', $user->passwordHash());
        self::assertTrue($user->isSuperAdmin());
        self::assertSame('2026-01-01 12:00:00', $user->createdAt()->format('Y-m-d H:i:s'));

        $codes = array_map(static fn (Role $r): string => $r->code(), $user->roles());
        sort($codes);
        self::assertSame([Role::SUPER_ADMIN, Role::USER], $codes);
    }

    public function testOfEmailFindsUser(): void
    {
        $id = $this->user('jane@example.com')->id();
        $this->em->clear();

        self::assertTrue($id->equals($this->users->ofEmail(Email::fromString('Jane@Example.com'))?->id() ?? self::fail('User not found.')));
        self::assertNull($this->users->ofEmail(Email::fromString('nobody@example.com')));
    }

    public function testPageIsOrderedByEmail(): void
    {
        $this->user('carol@example.com');
        $this->user('alice@example.com');
        $this->user('bob@example.com');
        $this->em->clear();

        self::assertSame(3, $this->users->count());
        self::assertSame(['alice@example.com', 'bob@example.com'], $this->emails($this->users->page(0, 2)));
        self::assertSame(['carol@example.com'], $this->emails($this->users->page(2, 2)));
    }

    public function testCountSuperAdminsIgnoresRegularUsers(): void
    {
        $this->user('root@example.com', [Role::USER, Role::SUPER_ADMIN]);
        $this->user('admin@example.com', [Role::SUPER_ADMIN]);
        $this->user('jane@example.com');

        self::assertSame(2, $this->users->countSuperAdmins());
    }
}
