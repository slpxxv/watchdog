<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

use App\Identity\Domain\Acl\Exception\PrivilegeEscalation;
use App\Identity\Domain\Acl\Permission;
use App\Identity\Domain\Role\Role;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class User
{
    private string $id;
    private string $email;

    /** @var Collection<int, Role> */
    private Collection $roles;

    private \DateTimeImmutable $createdAt;

    private function __construct(
        UserId $id,
        Email $email,
        private string $passwordHash,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id->value;
        $this->email = $email->value;
        $this->roles = new ArrayCollection();
        $this->createdAt = $createdAt;
    }

    /**
     * @param list<Role> $roles
     */
    public static function register(UserId $id, Email $email, string $passwordHash, array $roles, \DateTimeImmutable $now): self
    {
        $user = new self($id, $email, $passwordHash, $now);
        $user->assignRoles($roles);

        return $user;
    }

    public function changePasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    /**
     * Replaces the role set.
     *
     * @param list<Role> $roles
     */
    public function assignRoles(array $roles): void
    {
        $this->roles->clear();
        foreach ($roles as $role) {
            if (!$this->roles->contains($role)) {
                $this->roles->add($role);
            }
        }
    }

    public function can(Permission $permission): bool
    {
        return $this->roles->exists(static fn (int $_, Role $r): bool => $r->grants($permission));
    }

    /**
     * Nobody can hand out a permission they don't hold themselves.
     *
     * @param list<Permission> $permissions
     */
    public function assertCanGrant(array $permissions): void
    {
        foreach ($permissions as $permission) {
            if (!$this->can($permission)) {
                throw PrivilegeEscalation::missing($permission);
            }
        }
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles->exists(static fn (int $_, Role $r): bool => $r->isSuperAdmin());
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return array_values(array_filter(Permission::cases(), $this->can(...)));
    }

    /**
     * @return list<Role>
     */
    public function roles(): array
    {
        return array_values($this->roles->toArray());
    }

    public function id(): UserId
    {
        return UserId::fromString($this->id);
    }

    public function email(): Email
    {
        return Email::fromString($this->email);
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
