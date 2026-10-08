<?php

declare(strict_types=1);

namespace App\Identity\Domain\Role\Exception;

use App\Shared\Domain\Error\DomainError;

final class InvalidRoleCode extends DomainError
{
    public static function for(string $code): self
    {
        return new self(\sprintf('Invalid role code "%s" (expected [a-z][a-z0-9_]{1,49}).', $code), 'identity.role.invalid_code', ['%code%' => $code]);
    }
}
