<?php

declare(strict_types=1);

namespace App\Shared\Domain;

enum Permission: string
{
    case UserView = 'user.view';
    case UserManage = 'user.manage';
    case RoleView = 'role.view';
    case RoleManage = 'role.manage';
}
