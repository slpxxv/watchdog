<?php

declare(strict_types=1);

namespace Watchdog\Identity\Domain\Role\Exception;

use Watchdog\Shared\Domain\Error\Conflict;
use Watchdog\Shared\Domain\Error\DomainError;

final class RoleAlreadyExists extends DomainError implements Conflict
{
    public static function withCode(string $code): self
    {
        return new self(
            message: \sprintf('Role "%s" already exists.', $code),
            messageKey: 'identity.role.already_exists',
            messageParameters: ['%code%' => $code],
        );
    }
}
