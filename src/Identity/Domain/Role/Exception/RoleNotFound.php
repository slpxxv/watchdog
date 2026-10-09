<?php

declare(strict_types=1);

namespace Watchdog\Identity\Domain\Role\Exception;

use Watchdog\Shared\Domain\Error\DomainError;
use Watchdog\Shared\Domain\Error\NotFound;

final class RoleNotFound extends DomainError implements NotFound
{
    public static function withCode(string $code): self
    {
        return new self(
            message: \sprintf('Role "%s" not found.', $code),
            messageKey: 'identity.role.not_found',
            messageParameters: ['%code%' => $code],
        );
    }
}
