<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Command\CreateRole;

use Watchdog\Identity\Domain\User\UserId;
use Watchdog\Shared\Domain\Permission;

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
