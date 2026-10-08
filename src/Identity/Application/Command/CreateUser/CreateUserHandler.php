<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\CreateUser;

use App\Identity\Application\Exception\WeakPassword;
use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Application\Service\RoleResolver;
use App\Identity\Domain\User\Email;
use App\Identity\Domain\User\Exception\UserAlreadyExists;
use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserId;
use App\Identity\Domain\User\UserRepository;
use Psr\Clock\ClockInterface;

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
            $this->users->nextIdentity(),
            $email,
            $this->hasher->hash($command->plainPassword),
            $this->roles->byCodes($command->roleCodes),
            $this->clock->now(),
        );
        $this->users->save($user);

        return $user->id();
    }
}
