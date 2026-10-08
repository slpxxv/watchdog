<?php

declare(strict_types=1);

namespace App\Identity\Domain\Role\Exception;

use App\Shared\Domain\DomainError;

final class CannotDeleteSystemRole extends DomainError
{
    public static function code(string $code): self
    {
        return new self(\sprintf('System role "%s" cannot be deleted.', $code), 'identity.role.system_delete', ['%code%' => $code]);
    }
}
