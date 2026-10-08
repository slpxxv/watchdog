<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Application\Command\CreateUser\CreateUser;
use App\Identity\Application\Command\CreateUser\CreateUserHandler;
use App\Identity\Domain\Role\Role;
use App\Shared\Domain\Permission;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthenticationTest extends WebTestCase
{
    private const string EMAIL = 'user@example.com';
    private const string ADMIN = 'root@example.com';
    private const string PASSWORD = 'correct horse battery staple';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $handler = self::getContainer()->get(CreateUserHandler::class);
        $handler(new CreateUser(self::EMAIL, self::PASSWORD));
        $handler(new CreateUser(self::ADMIN, self::PASSWORD, [Role::USER, Role::SUPER_ADMIN]));
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    public function testApiRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testUserCanLogInAndOut(): void
    {
        $this->client->jsonRequest('POST', '/api/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]);
        self::assertResponseIsSuccessful();
        self::assertResponseHasHeader('Content-Security-Policy');
        self::assertSame(self::EMAIL, $this->json()['email']);

        $this->client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
        self::assertSame([Role::USER], $this->json()['roles']);
        self::assertSame([], $this->json()['permissions']);

        $this->client->request('POST', '/api/logout');
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testSuperAdminHasEveryPermission(): void
    {
        $this->client->jsonRequest('POST', '/api/login', ['email' => self::ADMIN, 'password' => self::PASSWORD]);
        self::assertResponseIsSuccessful();

        $me = $this->json();
        self::assertSame([Role::SUPER_ADMIN, Role::USER], $me['roles'], 'sorted');
        self::assertSame(array_column(Permission::cases(), 'value'), $me['permissions']);
    }

    public function testInvalidCredentialsAreRejected(): void
    {
        $this->client->jsonRequest('POST', '/api/login', ['email' => self::EMAIL, 'password' => 'wrong password!!']);
        self::assertResponseStatusCodeSame(401);
        self::assertArrayHasKey('error', $this->json());

        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginRejectsNonJsonBody(): void
    {
        // What a cross-site HTML form could send: must not authenticate (login CSRF).
        $this->client->request('POST', '/api/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]);
        self::assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }
}
