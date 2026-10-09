<?php

declare(strict_types=1);

namespace Watchdog\Identity\Application\Command\CreateUser;

use Watchdog\Identity\Domain\Role\Role;

final readonly class CreateUser
{
    /**
     * @param list<string> $roleCodes
     */
    public function __construct(
        public string $email,
        #[\SensitiveParameter] public string $plainPassword,
        public array $roleCodes = [Role::USER],
    ) {
    }
}
