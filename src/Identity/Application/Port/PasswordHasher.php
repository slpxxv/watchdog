<?php

declare(strict_types=1);

namespace App\Identity\Application\Port;

interface PasswordHasher
{
    public function hash(string $plainPassword): string;
}
