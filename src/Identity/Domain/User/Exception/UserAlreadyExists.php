<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Exception;

use App\Identity\Domain\User\Email;
use App\Shared\Domain\DomainError;

final class UserAlreadyExists extends DomainError
{
    public static function withEmail(Email $email): self
    {
        return new self(\sprintf('User with email "%s" already exists.', $email->value), 'identity.user.already_exists', ['%email%' => $email->value]);
    }
}
