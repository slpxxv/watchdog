<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Identity\Domain\Role\Role;
use App\Identity\Domain\Role\RoleId;
use App\Identity\Domain\Role\RoleRepository;

final class InMemoryRoleRepository implements RoleRepository
{
    /** @var array<string, Role> */
    private array $roles = [];
    private int $sequence = 0;

    public function nextIdentity(): RoleId
    {
        return RoleId::fromString(\sprintf('00000000-0000-7000-9000-%012d', ++$this->sequence));
    }

    public function save(Role $role): void
    {
        $this->roles[$role->id()->value] = $role;
    }

    public function remove(Role $role): void
    {
        unset($this->roles[$role->id()->value]);
    }

    public function ofId(RoleId $id): ?Role
    {
        return $this->roles[$id->value] ?? null;
    }

    public function ofCode(string $code): ?Role
    {
        return array_find($this->roles, static fn (Role $r): bool => $r->code() === $code);
    }

    public function all(): array
    {
        return array_values($this->roles);
    }

    /**
     * Mirrors the system roles seeded by the migration.
     */
    public static function withSystemRoles(): self
    {
        $repo = new self();
        $hydrate = static function (Role $role, bool $superAdmin): Role {
            (fn () => [$this->superAdmin, $this->system] = [$superAdmin, true])->call($role);

            return $role;
        };
        $repo->save($hydrate(Role::create($repo->nextIdentity(), Role::SUPER_ADMIN, 'Super Admin', []), true));
        $repo->save($hydrate(Role::create($repo->nextIdentity(), Role::USER, 'User', []), false));

        return $repo;
    }
}
