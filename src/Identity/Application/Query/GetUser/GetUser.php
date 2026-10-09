<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Query\GetUser;

use Watchdog\Identity\Domain\User\UserId;

final readonly class GetUser
{
    public function __construct(public UserId $id)
    {
    }
}
