<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

use App\Identity\Domain\User\UserId;

interface CurrentActor
{
    public function id(): UserId;
}
