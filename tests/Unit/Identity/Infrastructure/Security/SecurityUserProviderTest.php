<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Infrastructure\Security\SecurityUserProvider;
use App\Shared\Domain\Permission;
use App\Tests\Double\Identities;
use App\Tests\Double\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class SecurityUserProviderTest extends TestCase
{
    private InMemoryUserRepository $users;
    private SecurityUserProvider $provider;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->provider = new SecurityUserProvider($this->users);
    }

    public function testItLoadsByNormalizedEmail(): void
    {
        $this->users->save(Identities::user('jane@example.com'));

        self::assertSame('jane@example.com', $this->provider->loadUserByIdentifier('  Jane@Example.COM ')->getUserIdentifier());
    }

    #[TestWith(['ghost@example.com'])]
    #[TestWith(['not an email'])]
    public function testUnknownOrInvalidIdentifierLooksLikeUnknownUser(string $identifier): void
    {
        try {
            $this->provider->loadUserByIdentifier($identifier);
            self::fail('Expected UserNotFoundException.');
        } catch (UserNotFoundException $e) {
            self::assertSame($identifier, $e->getUserIdentifier());
        }
    }

    public function testRefreshPicksUpPermissionChangesImmediately(): void
    {
        $user = Identities::user();
        $this->users->save($user);
        $sessionUser = $this->provider->loadUserByIdentifier('jane@example.com');

        $user->assignRoles([Identities::role('viewer', [Permission::RoleView])]);

        self::assertFalse($sessionUser->can(Permission::RoleView));
        self::assertTrue($this->provider->refreshUser($sessionUser)->can(Permission::RoleView));
    }

    public function testRefreshFailsForAUserThatNoLongerExists(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->provider->refreshUser(SecurityUser::fromUser(Identities::user()));
    }

    public function testRefreshRejectsForeignUserClasses(): void
    {
        self::assertFalse($this->provider->supportsClass(InMemoryUser::class));

        $this->expectException(UnsupportedUserException::class);
        $this->provider->refreshUser(new InMemoryUser('ghost', null));
    }

    public function testUpgradePasswordStoresTheNewHash(): void
    {
        $user = Identities::user(passwordHash: 'old');
        $this->users->save($user);

        $this->provider->upgradePassword(SecurityUser::fromUser($user), 'rehashed');

        self::assertSame('rehashed', $this->users->ofId($user->id())?->passwordHash());
    }
}
