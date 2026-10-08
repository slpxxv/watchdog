<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Exception;

use App\Shared\Domain\DomainError;

final class LastSuperAdmin extends DomainError
{
    public static function create(): self
    {
        return new self('The last super admin cannot lose that role.', 'identity.user.last_super_admin');
    }
}
