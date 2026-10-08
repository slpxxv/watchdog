<?php

declare(strict_types=1);

namespace App\Identity\Domain\Role\Exception;

use App\Shared\Domain\Error\DomainError;

final class RoleAlreadyExists extends DomainError
{
    public static function withCode(string $code): self
    {
        return new self(\sprintf('Role "%s" already exists.', $code), 'identity.role.already_exists', ['%code%' => $code]);
    }
}
