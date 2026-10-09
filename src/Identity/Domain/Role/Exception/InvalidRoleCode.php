<?php

declare(strict_types=1);

namespace Watchdog\Identity\Domain\Role\Exception;

use Watchdog\Shared\Domain\Error\DomainError;

final class InvalidRoleCode extends DomainError
{
    public static function for(string $code): self
    {
        return new self(
            message: \sprintf('Invalid role code "%s" (expected [a-z][a-z0-9_]{1,49}).', $code),
            messageKey: 'identity.role.invalid_code',
            messageParameters: ['%code%' => $code],
        );
    }
}
