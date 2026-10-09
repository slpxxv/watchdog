<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Command\CreateUser;

use Psr\Clock\ClockInterface;
use Watchdog\Identity\Application\Exception\WeakPassword;
use Watchdog\Identity\Application\Port\PasswordHasher;
use Watchdog\Identity\Application\Service\RoleResolver;
use Watchdog\Identity\Domain\User\Email;
use Watchdog\Identity\Domain\User\Exception\UserAlreadyExists;
use Watchdog\Identity\Domain\User\User;
use Watchdog\Identity\Domain\User\UserId;
use Watchdog\Identity\Domain\User\UserRepository;

final readonly class CreateUserHandler
{
    public const int MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private UserRepository $users,
        private RoleResolver $roles,
        private PasswordHasher $hasher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateUser $command): UserId
    {
        $email = Email::fromString($command->email);

        if (mb_strlen($command->plainPassword) < self::MIN_PASSWORD_LENGTH) {
            throw WeakPassword::tooShort(self::MIN_PASSWORD_LENGTH);
        }

        if (null !== $this->users->ofEmail($email)) {
            throw UserAlreadyExists::withEmail($email);
        }

        $user = User::register(
            id: $this->users->nextIdentity(),
            email: $email,
            passwordHash: $this->hasher->hash($command->plainPassword),
            roles: $this->roles->byCodes($command->roleCodes),
            now: $this->clock->now(),
        );

        $this->users->save($user);

        return $user->id();
    }
}
