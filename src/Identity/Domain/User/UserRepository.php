<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

interface UserRepository
{
    public function nextIdentity(): UserId;

    public function save(User $user): void;

    public function ofId(UserId $id): ?User;

    public function ofEmail(Email $email): ?User;

    /**
     * @return list<User> ordered by email
     */
    public function page(int $offset, int $limit): array;

    public function count(): int;

    public function countSuperAdmins(): int;
}
