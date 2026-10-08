<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\CreateRole;

use App\Identity\Domain\User\UserId;
use App\Shared\Domain\Permission;

final readonly class CreateRole
{
    /**
     * @param list<Permission> $permissions
     */
    public function __construct(
        public UserId $actorId,
        public string $code,
        public string $name,
        public array $permissions,
    ) {
    }
}
