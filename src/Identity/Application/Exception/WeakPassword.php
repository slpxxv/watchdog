<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Exception;

use Watchdog\Shared\Domain\Error\DomainError;

final class WeakPassword extends DomainError
{
    public static function tooShort(int $min): self
    {
        return new self(
            message: \sprintf('Password must be at least %d characters long.', $min),
            messageKey: 'identity.password.too_short',
            messageParameters: ['%min%' => $min],
        );
    }
}
