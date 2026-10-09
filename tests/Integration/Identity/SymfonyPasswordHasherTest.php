<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class SymfonyPasswordHasherTest extends KernelTestCase
{
    public function testHashVerifiesWithSecurityUserHasher(): void
    {
        $hash = self::getContainer()->get(PasswordHasher::class)->hash('correct horse battery staple');
        $hasher = self::getContainer()->get(PasswordHasherFactoryInterface::class)->getPasswordHasher(SecurityUser::class);

        self::assertNotSame('correct horse battery staple', $hash);
        self::assertTrue($hasher->verify($hash, 'correct horse battery staple'));
        self::assertFalse($hasher->verify($hash, 'wrong password'));
    }
}
