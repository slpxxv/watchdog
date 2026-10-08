<?php

declare(strict_types=1);

namespace App\Identity\Domain\Role;

use App\Identity\Domain\Acl\Permission;
use App\Identity\Domain\Role\Exception\InvalidRoleCode;

class Role
{
    public const string SUPER_ADMIN = 'super_admin';
    public const string USER = 'user';

    private string $id;

    /** @var list<string> */
    private array $permissions = [];

    /**
     * @param list<Permission> $permissions
     */
    private function __construct(
        RoleId $id,
        private string $code,
        private string $name,
        array $permissions,
        private bool $superAdmin = false,
        private bool $system = false,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{1,49}$/', $code)) {
            throw InvalidRoleCode::for($code);
        }

        $this->id = $id->value;
        $this->rename($name);
        $this->grant($permissions);
    }

    /**
     * @param list<Permission> $permissions
     */
    public static function create(RoleId $id, string $code, string $name, array $permissions): self
    {
        return new self($id, $code, $name, $permissions);
    }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > 100) {
            throw new \InvalidArgumentException('Role name must be 1-100 characters.');
        }

        $this->name = $name;
    }

    /**
     * Replaces the permission set.
     *
     * @param list<Permission> $permissions
     */
    public function grant(array $permissions): void
    {
        $values = array_unique(array_map(static fn (Permission $p): string => $p->value, $permissions));
        sort($values);
        $this->permissions = $values;
    }

    public function grants(Permission $permission): bool
    {
        return $this->superAdmin || \in_array($permission->value, $this->permissions, true);
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        if ($this->superAdmin) {
            return Permission::cases();
        }

        // Catalogue order; permissions removed from the catalogue are silently ignored.
        return array_values(array_filter(Permission::cases(), $this->grants(...)));
    }

    public function id(): RoleId
    {
        return RoleId::fromString($this->id);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** Super admin implicitly holds every permission, including ones added later. */
    public function isSuperAdmin(): bool
    {
        return $this->superAdmin;
    }

    /** System roles are seeded by migrations and cannot be deleted. */
    public function isSystem(): bool
    {
        return $this->system;
    }
}
