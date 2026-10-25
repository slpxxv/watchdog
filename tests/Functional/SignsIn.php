<?php

declare(strict_types=1);

namespace Watchdog\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Logs in through the real form (CSRF token included), the way a browser does.
 */
trait SignsIn
{
    private const string PASSWORD = 'correct horse battery staple';

    private function logIn(KernelBrowser $client, string $email, string $password = self::PASSWORD): void
    {
        $client->request('GET', '/login');
        $client->submitForm('Zaloguj się', ['_username' => $email, '_password' => $password]);
    }
}
