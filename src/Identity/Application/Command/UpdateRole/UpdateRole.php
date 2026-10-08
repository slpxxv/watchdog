<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\UpdateRole;

use App\Identity\Domain\Acl\Permission;
use App\Identity\Domain\Role\RoleId;
use App\Identity\Domain\User\UserId;

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
