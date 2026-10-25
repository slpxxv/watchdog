<?php

declare(strict_types=1);

namespace Watchdog\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Watchdog\Identity\Application\Command\CreateUser;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Shared\Domain\Permission;

final class AuthenticationTest extends WebTestCase
{
    use SignsIn;

    private const string EMAIL = 'user@example.com';
    private const string ADMIN = 'root@example.com';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $createUser = self::getContainer()->get(CreateUser::class);
        $createUser(self::EMAIL, self::PASSWORD);
        $createUser(self::ADMIN, self::PASSWORD, [Role::USER, Role::SUPER_ADMIN]);
    }

    public function testPagesRequireAuthentication(): void
    {
        $this->client->request('GET', '/projects');
        self::assertResponseRedirects('/login');

        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertResponseHasHeader('Content-Security-Policy');
    }

    public function testUserCanLogInAndOut(): void
    {
        $this->logIn($this->client, self::EMAIL);
        self::assertResponseRedirects('/');
        $this->client->followRedirects();

        $this->client->request('GET', '/debug');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.kv', self::EMAIL);

        $this->client->submitForm('Wyloguj');
        self::assertSelectorExists('form[action="/login"]');

        $this->client->request('GET', '/debug');
        self::assertSelectorExists('form[action="/login"]');
    }

    public function testSuperAdminHasEveryPermission(): void
    {
        $this->logIn($this->client, self::ADMIN);
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/debug');
        $text = $crawler->filter('table.kv')->first()->text();
        self::assertStringContainsString(implode(', ', array_column(Permission::cases(), 'value')), $text);
    }

    public function testInvalidCredentialsAreRejected(): void
    {
        $this->logIn($this->client, self::EMAIL, 'wrong password!!');
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert', 'Nieprawidłowy e-mail lub hasło.');

        $this->client->request('GET', '/projects');
        self::assertResponseRedirects('/login');
    }

    public function testLoginNeedsTheCsrfToken(): void
    {
        $this->client->request('POST', '/login', ['_username' => self::EMAIL, '_password' => self::PASSWORD, '_csrf_token' => 'forged']);
        self::assertResponseRedirects('/login');

        $this->client->request('GET', '/projects');
        self::assertResponseRedirects('/login');
    }
}
