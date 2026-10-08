<?php

declare(strict_types=1);

namespace App\Identity\Application\Exception;

use App\Shared\Domain\DomainError;

final class WeakPassword extends DomainError
{
    public static function tooShort(int $min): self
    {
        return new self(\sprintf('Password must be at least %d characters long.', $min), 'identity.password.too_short', ['%min%' => $min]);
    }
}
