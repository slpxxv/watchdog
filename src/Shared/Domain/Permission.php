<?php

declare(strict_types=1);

namespace Watchdog\Shared\Domain;

enum Permission: string
{
    case UserView = 'user.view';
    case UserManage = 'user.manage';
    case RoleView = 'role.view';
    case RoleManage = 'role.manage';
    case ProjectView = 'project.view';
    case ProjectManage = 'project.manage';
}
