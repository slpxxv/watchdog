<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Query;

use Watchdog\Identity\Application\Dto\UserDto;
use Watchdog\Identity\Domain\User\Exception\UserNotFound;
use Watchdog\Identity\Domain\User\UserId;
use Watchdog\Identity\Domain\User\UserRepository;

final readonly class GetUser
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(UserId $id): UserDto
    {
        $user = $this->users->ofId($id) ?? throw UserNotFound::withId($id);

        return UserDto::fromUser($user);
    }
}
