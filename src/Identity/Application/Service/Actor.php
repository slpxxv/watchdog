<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Service;

use Watchdog\Identity\Domain\User\User;
use Watchdog\Identity\Domain\User\UserId;
use Watchdog\Identity\Domain\User\UserRepository;

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
