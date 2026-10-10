<?php

declare(strict_types=1);

namespace Watchdog\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Watchdog\Identity\Application\Command\CreateUser\CreateUser;
use Watchdog\Identity\Application\Command\CreateUser\CreateUserHandler;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Identity\Domain\Role\RoleRepository;
use Watchdog\Logging\Application\Command\PurgeProjectLogs\PurgeProjectLogs;
use Watchdog\Logging\Domain\Source\SourceRepository;
use Watchdog\Logging\Domain\Source\SourceToken;
use Watchdog\Shared\Domain\Permission;

final class SourceApiTest extends WebTestCase
{
    private const string ADMIN = 'root@example.com';
    private const string VIEWER = 'viewer@example.com';
    private const string PASSWORD = 'correct horse battery staple';
    private const string UNKNOWN = '01890000-0000-7000-8000-000000000000';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $roles = self::getContainer()->get(RoleRepository::class);
        $roles->save(Role::create($roles->nextIdentity(), 'project_viewer', 'Project viewer', [Permission::ProjectView]));

        $createUser = self::getContainer()->get(CreateUserHandler::class);
        $createUser(new CreateUser(self::ADMIN, self::PASSWORD, [Role::USER, Role::SUPER_ADMIN]));
        $createUser(new CreateUser(self::VIEWER, self::PASSWORD, [Role::USER, 'project_viewer']));
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

    private function createProject(): string
    {
        $this->client->jsonRequest('POST', '/api/projects', ['name' => 'Sklep']);
        self::assertResponseStatusCodeSame(201);
        $id = $this->json()['id'];
        self::assertIsString($id);

        return $id;
    }

    /**
     * @return array{id: string, token: string}
     */
    private function createSource(string $projectId, string $name = 'API'): array
    {
        $this->client->jsonRequest('POST', "/api/projects/{$projectId}/sources", ['name' => $name]);
        self::assertResponseStatusCodeSame(201);
        $body = $this->json();
        self::assertIsArray($body['source']);
        self::assertIsString($body['source']['id']);
        self::assertIsString($body['token']);

        return ['id' => $body['source']['id'], 'token' => $body['token']];
    }

    private function sourceByToken(string $token): mixed
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(SourceRepository::class)->ofTokenHash(SourceToken::hashOf($token));
    }

    public function testTokenIsShownOnceAndOnlyItsHashIsStored(): void
    {
        $this->logIn(self::ADMIN);
        $projectId = $this->createProject();

        $source = $this->createSource($projectId);

        self::assertStringStartsWith('wd_', $source['token']);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertNotNull($this->sourceByToken($source['token']));

        $this->client->request('GET', "/api/projects/{$projectId}/sources");
        self::assertResponseIsSuccessful();
        $list = $this->json();
        self::assertCount(1, $list);
        self::assertIsArray($list[0]);
        self::assertSame(substr($source['token'], 0, 8), $list[0]['tokenPrefix']);
        self::assertArrayNotHasKey('token', $list[0]);
        self::assertStringNotContainsString($source['token'], (string) $this->client->getResponse()->getContent());
    }

    public function testRotationReplacesTheTokenAndRevokeIsFinal(): void
    {
        $this->logIn(self::ADMIN);
        $source = $this->createSource($this->createProject());

        $this->client->request('POST', "/api/sources/{$source['id']}/rotate-token");
        self::assertResponseIsSuccessful();
        $rotated = $this->json()['token'];
        self::assertIsString($rotated);
        self::assertNull($this->sourceByToken($source['token']), 'old token no longer matches');
        self::assertNotNull($this->sourceByToken($rotated));

        $this->client->request('POST', "/api/sources/{$source['id']}/revoke");
        self::assertResponseStatusCodeSame(204);

        $this->client->request('POST', "/api/sources/{$source['id']}/rotate-token");
        self::assertResponseStatusCodeSame(409);
        self::assertSame('source.revoked', $this->json()['messageKey']);
    }

    public function testDeletingTheProjectRevokesItsSourcesAndQueuesTheLogPurge(): void
    {
        $this->logIn(self::ADMIN);
        $projectId = $this->createProject();
        $source = $this->createSource($projectId);

        $this->client->request('DELETE', "/api/projects/{$projectId}");
        self::assertResponseStatusCodeSame(204);

        $stored = $this->sourceByToken($source['token']);
        self::assertInstanceOf(\Watchdog\Logging\Domain\Source\Source::class, $stored);
        self::assertTrue($stored->isRevoked());

        // Logs go in the background: one purge message queued for the worker.
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        self::assertEquals([new PurgeProjectLogs($projectId)], array_map(static fn (Envelope $e): object => $e->getMessage(), $transport->getSent()));
    }

    public function testUnknownProjectOrSourceIsNotFound(): void
    {
        $this->logIn(self::ADMIN);

        $this->client->request('GET', '/api/projects/'.self::UNKNOWN.'/sources');
        self::assertResponseStatusCodeSame(404);
        self::assertSame('project.not_found', $this->json()['messageKey']);

        $this->client->jsonRequest('POST', '/api/projects/'.self::UNKNOWN.'/sources', ['name' => 'API']);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/api/sources/'.self::UNKNOWN.'/revoke');
        self::assertResponseStatusCodeSame(404);
        self::assertSame('source.not_found', $this->json()['messageKey']);
    }

    public function testBlankNameIsRejected(): void
    {
        $this->logIn(self::ADMIN);
        $projectId = $this->createProject();

        $this->client->jsonRequest('POST', "/api/projects/{$projectId}/sources", ['name' => '   ']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testManagingSourcesNeedsSourceManage(): void
    {
        $this->logIn(self::ADMIN);
        $projectId = $this->createProject();
        $this->client->request('POST', '/api/logout');

        $this->logIn(self::VIEWER);
        $this->client->request('GET', "/api/projects/{$projectId}/sources");
        self::assertResponseStatusCodeSame(403);

        $this->client->jsonRequest('POST', "/api/projects/{$projectId}/sources", ['name' => 'API']);
        self::assertResponseStatusCodeSame(403);
    }
}
