<?php

declare(strict_types=1);

namespace App\Identity\Application\Command\AssignUserRoles;

use App\Identity\Domain\User\UserId;

final readonly class AssignUserRoles
{
    /**
     * @param list<string> $roleCodes
     */
    public function __construct(
        public UserId $actorId,
        public UserId $userId,
        public array $roleCodes,
    ) {
    }
}
