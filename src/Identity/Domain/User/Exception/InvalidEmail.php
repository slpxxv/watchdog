<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Exception;

use App\Shared\Domain\Error\DomainError;

final class InvalidEmail extends DomainError
{
    public static function for(string $value): self
    {
        return new self(\sprintf('"%s" is not a valid email address.', $value), 'identity.email.invalid');
    }
}
