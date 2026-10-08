<?php

declare(strict_types=1);

namespace App\Identity\Domain\Acl\Exception;

use App\Identity\Domain\Acl\Permission;
use App\Shared\Domain\DomainError;

final class PrivilegeEscalation extends DomainError
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
