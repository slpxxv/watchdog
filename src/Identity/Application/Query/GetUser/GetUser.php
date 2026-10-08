<?php

declare(strict_types=1);

namespace App\Identity\Application\Query\GetUser;

use App\Identity\Domain\User\UserId;

final readonly class GetUser
{
    public function __construct(public UserId $id)
    {
    }
}
