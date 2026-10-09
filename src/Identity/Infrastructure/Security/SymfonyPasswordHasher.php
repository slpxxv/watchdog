<?php

declare(strict_types=1);

namespace Watchdog\Identity\Infrastructure\Security;

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Watchdog\Identity\Application\Port\PasswordHasher;

final readonly class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(private PasswordHasherFactoryInterface $factory)
    {
    }

    public function hash(#[\SensitiveParameter] string $plainPassword): string
    {
        return $this->factory->getPasswordHasher(SecurityUser::class)->hash($plainPassword);
    }
}
