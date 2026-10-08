<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\User\Email;
use App\Identity\Domain\User\Exception\InvalidEmail;
use App\Identity\Domain\User\UserId;
use App\Identity\Domain\User\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class SecurityUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(private UserRepository $users)
    {
    }

    public function loadUserByIdentifier(string $identifier): SecurityUser
    {
        try {
            $user = $this->users->ofEmail(Email::fromString($identifier));
        } catch (InvalidEmail) {
            $user = null;
        }

        if (null === $user) {
            $e = new UserNotFoundException();
            $e->setUserIdentifier($identifier);

            throw $e;
        }

        return SecurityUser::fromUser($user);
    }

    public function refreshUser(UserInterface $user): SecurityUser
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(\sprintf('Unsupported user class "%s".', $user::class));
        }

        $domainUser = $this->users->ofId(UserId::fromString($user->id))
            ?? throw new UserNotFoundException();

        return SecurityUser::fromUser($domainUser);
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof SecurityUser || null === $domainUser = $this->users->ofId(UserId::fromString($user->id))) {
            return;
        }

        $domainUser->changePasswordHash($newHashedPassword);
        $this->users->save($domainUser);
    }
}
