<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Dto;

use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Identity\Domain\User\User;
use Watchdog\Shared\Domain\Permission;

final readonly class UserDto
{
    /**
     * @param list<string> $roles       role codes, sorted
     * @param list<string> $permissions effective permissions (union over roles)
     */
    public function __construct(
        public string $id,
        public string $email,
        public array $roles,
        public array $permissions,
        public string $createdAt,
    ) {
    }

    public static function fromUser(User $user): self
    {
        $roles = array_map(static fn (Role $r): string => $r->code(), $user->roles());
        sort($roles); // the DB returns the role collection in no particular order

        return new self(
            id: $user->id()->value,
            email: $user->email()->value,
            roles: $roles,
            permissions: array_map(static fn (Permission $p): string => $p->value, $user->permissions()),
            createdAt: $user->createdAt()->format(\DATE_ATOM),
        );
    }
}
