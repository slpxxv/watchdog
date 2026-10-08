<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Acl\Permission;
use App\Identity\Domain\User\User;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Reloaded from the DB on every request (SecurityUserProvider::refreshUser), so permission changes apply immediately.
 */
final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const string ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    /**
     * @param non-empty-string $email
     * @param list<string>     $permissions
     */
    private function __construct(
        public string $id,
        private string $email,
        private ?string $password,
        private bool $superAdmin,
        private array $permissions,
    ) {
    }

    public static function fromUser(User $user): self
    {
        /** @var non-empty-string $email */
        $email = $user->email()->value;

        return new self(
            $user->id()->value,
            $email,
            $user->passwordHash(),
            $user->isSuperAdmin(),
            array_map(static fn (Permission $p): string => $p->value, $user->permissions()),
        );
    }

    public function can(Permission $permission): bool
    {
        return $this->superAdmin || \in_array($permission->value, $this->permissions, true);
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * Coarse Symfony roles only; fine-grained checks go through PermissionVoter.
     * Losing ROLE_SUPER_ADMIN changes this list, which makes Symfony log the session out.
     */
    public function getRoles(): array
    {
        return $this->superAdmin ? ['ROLE_USER', self::ROLE_SUPER_ADMIN] : ['ROLE_USER'];
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Never serialize the password hash into the session.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'password' => null,
            'superAdmin' => $this->superAdmin,
            'permissions' => $this->permissions,
        ];
    }
}
