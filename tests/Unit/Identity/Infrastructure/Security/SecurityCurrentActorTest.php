<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\SecurityCurrentActor;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Tests\Double\Identities;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

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
