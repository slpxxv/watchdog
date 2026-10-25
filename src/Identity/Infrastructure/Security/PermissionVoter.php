<?php

declare(strict_types=1);

namespace Watchdog\Identity\Infrastructure\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Watchdog\Shared\Domain\Permission;

/**
 * @extends Voter<string, mixed>
 */
final class PermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return null !== Permission::tryFrom($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof SecurityUser && $user->can(Permission::from($attribute));
    }
}
