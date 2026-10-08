<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Identity\Domain\User\Email;
use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserId;
use App\Identity\Domain\User\UserRepository;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];
    private int $sequence = 0;

    public function nextIdentity(): UserId
    {
        return UserId::fromString(\sprintf('00000000-0000-7000-8000-%012d', ++$this->sequence));
    }

    public function save(User $user): void
    {
        $this->users[$user->id()->value] = $user;
    }

    public function ofId(UserId $id): ?User
    {
        return $this->users[$id->value] ?? null;
    }

    public function ofEmail(Email $email): ?User
    {
        return array_find($this->users, static fn (User $u): bool => $u->email()->equals($email));
    }

    public function page(int $offset, int $limit): array
    {
        return \array_slice(array_values($this->users), $offset, $limit);
    }

    public function count(): int
    {
        return \count($this->users);
    }

    public function countSuperAdmins(): int
    {
        return \count(array_filter($this->users, static fn (User $u): bool => $u->isSuperAdmin()));
    }
}
