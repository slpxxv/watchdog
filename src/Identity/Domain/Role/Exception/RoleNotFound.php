<?php

declare(strict_types=1);

namespace App\Identity\Domain\Role\Exception;

use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Error\NotFound;

final class RoleNotFound extends DomainError implements NotFound
{
    public static function withCode(string $code): self
    {
        return new self(\sprintf('Role "%s" not found.', $code), 'identity.role.not_found', ['%code%' => $code]);
    }
}
