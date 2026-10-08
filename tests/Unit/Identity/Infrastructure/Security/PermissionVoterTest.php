<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\PermissionVoter;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\Permission;
use App\Tests\Double\Identities;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class PermissionVoterTest extends TestCase
{
    private function vote(UserInterface $user, string $attribute): int
    {
        return (new PermissionVoter())->vote(new UsernamePasswordToken($user, 'main', $user->getRoles()), null, [$attribute]);
    }

    private function viewer(): SecurityUser
    {
        return SecurityUser::fromUser(Identities::user(roles: [Identities::role('viewer', [Permission::RoleView])]));
    }

    public function testItGrantsAHeldPermission(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->viewer(), Permission::RoleView->value));
    }

    public function testItDeniesAMissingPermission(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->viewer(), Permission::RoleManage->value));
    }

    #[TestWith(['ROLE_USER'])]
    #[TestWith(['role.unknown'])]
    public function testItAbstainsOnAttributesOutsideTheCatalogue(string $attribute): void
    {
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($this->viewer(), $attribute));
    }

    public function testItDeniesUsersFromOtherProviders(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new InMemoryUser('ghost', null, ['ROLE_USER']), Permission::RoleView->value));
    }
}
