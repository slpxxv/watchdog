<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Exception;

use App\Identity\Domain\User\Email;
use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Error\DomainError;

final class UserAlreadyExists extends DomainError implements Conflict
{
    public static function withEmail(Email $email): self
    {
        return new self(
            message: \sprintf('User with email "%s" already exists.', $email->value),
            messageKey: 'identity.user.already_exists',
            messageParameters: ['%email%' => $email->value],
        );
    }
}
