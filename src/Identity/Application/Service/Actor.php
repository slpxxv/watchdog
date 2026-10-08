<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserId;
use App\Identity\Domain\User\UserRepository;

final readonly class Actor
{
    public function __construct(private UserRepository $users)
    {
    }

    public function load(UserId $id): User
    {
        return $this->users->ofId($id) ?? throw new \LogicException('Acting user no longer exists.');
    }
}
