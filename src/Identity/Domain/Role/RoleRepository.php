<?php

declare(strict_types=1);

namespace App\Identity\Domain\Role;

interface RoleRepository
{
    public function nextIdentity(): RoleId;

    public function save(Role $role): void;

    public function remove(Role $role): void;

    public function ofId(RoleId $id): ?Role;

    public function ofCode(string $code): ?Role;

    /**
     * @return list<Role>
     */
    public function all(): array;
}
