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
        return new self(\sprintf('Cannot grant "%s" without holding it.', $permission->value), 'identity.acl.missing_permission', ['%permission%' => $permission->value]);
    }

    public static function superAdminOnly(): self
    {
        return new self('Only a super admin can manage super admins.', 'identity.acl.super_admin_only');
    }
}
