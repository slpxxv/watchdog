<?php

declare(strict_types=1);

namespace App\Identity\Application\Dto;

use App\Identity\Domain\Role\Role;
use App\Identity\Domain\User\User;
use App\Shared\Domain\Permission;

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
            $user->id()->value,
            $user->email()->value,
            $roles,
            array_map(static fn (Permission $p): string => $p->value, $user->permissions()),
            $user->createdAt()->format(\DATE_ATOM),
        );
    }
}
