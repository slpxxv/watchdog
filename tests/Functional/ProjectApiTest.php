<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Application\Command\CreateUser\CreateUser;
use App\Identity\Application\Command\CreateUser\CreateUserHandler;
use App\Identity\Domain\Role\Role;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProjectApiTest extends WebTestCase
{
    private const string USER = 'user@example.com';
    private const string ADMIN = 'root@example.com';
    private const string PASSWORD = 'correct horse battery staple';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $handler = self::getContainer()->get(CreateUserHandler::class);
        $handler(new CreateUser(self::USER, self::PASSWORD));
        $handler(new CreateUser(self::ADMIN, self::PASSWORD, [Role::USER, Role::SUPER_ADMIN]));
    }

    /**
     * @return array<mixed>
     */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    private function logIn(string $email): void
    {
        $this->client->jsonRequest('POST', '/api/login', ['email' => $email, 'password' => self::PASSWORD]);
        self::assertResponseIsSuccessful();
    }

    public function testProjectLifecycle(): void
    {
        $this->logIn(self::ADMIN);

        $this->client->jsonRequest('POST', '/api/projects', ['name' => '  Watchdog  ']);
        self::assertResponseStatusCodeSame(201);
        $created = $this->json();
        self::assertSame('Watchdog', $created['name']);
        $id = $created['id'];
        self::assertIsString($id);
        self::assertResponseHeaderSame('Location', '/api/projects/'.$id);

        $this->client->jsonRequest('PATCH', '/api/projects/'.$id, ['name' => 'Renamed']);
        self::assertResponseIsSuccessful();
        self::assertSame('Renamed', $this->json()['name']);

        $this->client->request('GET', '/api/projects/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSame('Renamed', $this->json()['name']);

        $this->client->request('GET', '/api/projects');
        self::assertResponseIsSuccessful();
        self::assertSame([$id], array_column($this->json(), 'id'));
    }

    public function testInvalidNameIsRejected(): void
    {
        $this->logIn(self::ADMIN);

        $this->client->jsonRequest('POST', '/api/projects', ['name' => '   ']);
        self::assertResponseStatusCodeSame(422);

        $this->client->jsonRequest('POST', '/api/projects', ['name' => str_repeat('x', 101)]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testUnknownProjectIsNotFound(): void
    {
        $this->logIn(self::ADMIN);

        $this->client->request('GET', '/api/projects/01890000-0000-7000-8000-000000000000');
        self::assertResponseStatusCodeSame(404);

        $this->client->jsonRequest('PATCH', '/api/projects/01890000-0000-7000-8000-000000000000', ['name' => 'x']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testUserWithoutPermissionIsForbidden(): void
    {
        $this->logIn(self::USER);

        $this->client->request('GET', '/api/projects');
        self::assertResponseStatusCodeSame(403);

        $this->client->jsonRequest('POST', '/api/projects', ['name' => 'Nope']);
        self::assertResponseStatusCodeSame(403);
    }
}
