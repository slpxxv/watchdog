<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Command;

use Psr\Clock\ClockInterface;
use Watchdog\Identity\Application\Exception\WeakPassword;
use Watchdog\Identity\Application\Port\PasswordHasher;
use Watchdog\Identity\Application\Service\RoleResolver;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Identity\Domain\User\Email;
use Watchdog\Identity\Domain\User\Exception\UserAlreadyExists;
use Watchdog\Identity\Domain\User\User;
use Watchdog\Identity\Domain\User\UserId;
use Watchdog\Identity\Domain\User\UserRepository;

final readonly class CreateUser
{
    public const int MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private UserRepository $users,
        private RoleResolver $roles,
        private PasswordHasher $hasher,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string> $roleCodes
     */
    public function __invoke(
        string $email,
        #[\SensitiveParameter] string $plainPassword,
        array $roleCodes = [Role::USER],
    ): UserId {
        $address = Email::fromString($email);

        if (mb_strlen($plainPassword) < self::MIN_PASSWORD_LENGTH) {
            throw WeakPassword::tooShort(self::MIN_PASSWORD_LENGTH);
        }

        if (null !== $this->users->ofEmail($address)) {
            throw UserAlreadyExists::withEmail($address);
        }

        $user = User::register(
            id: $this->users->nextIdentity(),
            email: $address,
            passwordHash: $this->hasher->hash($plainPassword),
            roles: $this->roles->byCodes($roleCodes),
            now: $this->clock->now(),
        );

        $this->users->save($user);

        return $user->id();
    }
}
