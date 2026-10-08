<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\PasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

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
