<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Port;

use Watchdog\Identity\Domain\User\UserId;

interface CurrentActor
{
    public function id(): UserId;
}
