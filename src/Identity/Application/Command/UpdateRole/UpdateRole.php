<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Command\UpdateRole;

use Watchdog\Identity\Domain\Role\RoleId;
use Watchdog\Identity\Domain\User\UserId;
use Watchdog\Shared\Domain\Permission;

final readonly class UpdateRole
{
    /**
     * @param list<Permission> $permissions
     */
    public function __construct(
        public UserId $actorId,
        public RoleId $id,
        public string $name,
        public array $permissions,
    ) {
    }
}
