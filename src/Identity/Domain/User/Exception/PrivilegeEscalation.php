<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Exception;

use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Error\Forbidden;
use App\Shared\Domain\Permission;

final class PrivilegeEscalation extends DomainError implements Forbidden
{
    public static function missing(Permission $permission): self
    {
        return new self(
            message: \sprintf('Cannot grant "%s" without holding it.', $permission->value),
            messageKey: 'identity.acl.missing_permission',
            messageParameters: ['%permission%' => $permission->value],
        );
    }

    public static function superAdminOnly(): self
    {
        return new self(
            message: 'Only a super admin can manage super admins.',
            messageKey: 'identity.acl.super_admin_only',
        );
    }
}
