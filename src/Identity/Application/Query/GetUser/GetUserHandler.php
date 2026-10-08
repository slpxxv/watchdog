<?php

declare(strict_types=1);

namespace App\Identity\Application\Query\GetUser;

use App\Identity\Application\Dto\UserDto;
use App\Identity\Domain\User\Exception\UserNotFound;
use App\Identity\Domain\User\UserRepository;

final readonly class GetUserHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(GetUser $query): UserDto
    {
        $user = $this->users->ofId($query->id) ?? throw UserNotFound::withId($query->id);

        return UserDto::fromUser($user);
    }
}
