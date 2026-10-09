<?php

declare(strict_types=1);

namespace App\Identity\Domain\Role\Exception;

use App\Shared\Domain\Error\DomainError;

final class CannotDeleteSystemRole extends DomainError
{
    public static function code(string $code): self
    {
        return new self(
            message: \sprintf('System role "%s" cannot be deleted.', $code),
            messageKey: 'identity.role.system_delete',
            messageParameters: ['%code%' => $code],
        );
    }
}
