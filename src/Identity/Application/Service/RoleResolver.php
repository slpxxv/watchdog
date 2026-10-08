<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Identity\Domain\Role\Exception\RoleNotFound;
use App\Identity\Domain\Role\Role;
use App\Identity\Domain\Role\RoleRepository;

final readonly class RoleResolver
{
    public function __construct(private RoleRepository $roles)
    {
    }

    /**
     * @param list<string> $codes
     *
     * @return list<Role>
     */
    public function byCodes(array $codes): array
    {
        return array_map(
            fn (string $code): Role => $this->roles->ofCode($code) ?? throw RoleNotFound::withCode($code),
            array_values(array_unique($codes)),
        );
    }
}
