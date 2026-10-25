<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Identity\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Watchdog\Identity\Infrastructure\Security\SecurityCurrentActor;
use Watchdog\Identity\Infrastructure\Security\SecurityUser;
use Watchdog\Tests\Double\Identities;

final class SecurityCurrentActorTest extends TestCase
{
    private function actor(?UserInterface $loggedIn): SecurityCurrentActor
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($loggedIn);

        return new SecurityCurrentActor($security);
    }

    public function testItReturnsTheLoggedInUserId(): void
    {
        $user = Identities::user();

        self::assertTrue($user->id()->equals($this->actor(SecurityUser::fromUser($user))->id()));
    }

    public function testAnonymousRequestIsDenied(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->actor(null)->id();
    }

    public function testForeignUserClassIsDenied(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->actor(new InMemoryUser('ghost', null))->id();
    }
}
