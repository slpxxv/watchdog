<?php

declare(strict_types=1);

namespace Watchdog\Identity\Domain\User\Exception;

use Watchdog\Shared\Domain\Error\DomainError;

final class InvalidEmail extends DomainError
{
    public static function for(string $value): self
    {
        return new self(
            message: \sprintf('"%s" is not a valid email address.', $value),
            messageKey: 'identity.email.invalid',
        );
    }
}
