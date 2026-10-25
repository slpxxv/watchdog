<?php

declare(strict_types=1);

namespace Watchdog\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Watchdog\Identity\Application\Command\CreateUser;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Identity\Domain\Role\RoleRepository;
use Watchdog\Logging\Application\Command\CreateSource;
use Watchdog\Logging\Application\Message\PurgeProjectLogsMessage;
use Watchdog\Logging\Domain\Source\Source;
use Watchdog\Logging\Domain\Source\SourceRepository;
use Watchdog\Logging\Domain\Source\SourceToken;
use Watchdog\Project\Application\Command\CreateProject;
use Watchdog\Shared\Domain\Permission;

final class SourcePagesTest extends WebTestCase
{
    use SignsIn;

    private const string ADMIN = 'root@example.com';
    private const string VIEWER = 'viewer@example.com';
    private const string LOG_VIEWER = 'logs@example.com';
    private const string UNKNOWN = '01890000-0000-7000-8000-000000000000';

    private KernelBrowser $client;
    private string $projectId;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $roles = self::getContainer()->get(RoleRepository::class);
        $roles->save(Role::create($roles->nextIdentity(), 'project_viewer', 'Project viewer', [Permission::ProjectView]));
        $roles->save(Role::create($roles->nextIdentity(), 'log_viewer', 'Log viewer', [Permission::LogView]));

        $createUser = self::getContainer()->get(CreateUser::class);
        $createUser(self::ADMIN, self::PASSWORD, [Role::USER, Role::SUPER_ADMIN]);
        $createUser(self::VIEWER, self::PASSWORD, [Role::USER, 'project_viewer']);
        $createUser(self::LOG_VIEWER, self::PASSWORD, [Role::USER, 'log_viewer']);

        $this->projectId = self::getContainer()->get(CreateProject::class)('Sklep')->value;
    }

    /**
     * Creates a source through the form; the page that answers the POST shows the token once.
     *
     * @return array{id: string, token: string, crawler: Crawler}
     */
    private function connectSource(string $name = 'API', string $platform = 'http'): array
    {
        $this->client->request('GET', "/projects/{$this->projectId}/sources/new");
        self::assertResponseIsSuccessful();
        $crawler = $this->client->submitForm('Utwórz źródło', ['source[name]' => $name, 'source[platform]' => $platform]);
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('.token-box code')->text();
        self::assertStringStartsWith('wd_', $token);
        $source = self::getContainer()->get(SourceRepository::class)->ofTokenHash(SourceToken::hashOf($token));
        self::assertInstanceOf(Source::class, $source);

        return ['id' => $source->id()->value, 'token' => $token, 'crawler' => $crawler];
    }

    private function sourceByToken(string $token): ?Source
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(SourceRepository::class)->ofTokenHash(SourceToken::hashOf($token));
    }

    public function testTokenIsShownOnceAndOnlyItsHashIsStored(): void
    {
        $this->logIn($this->client, self::ADMIN);

        $source = $this->connectSource();

        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertNotNull($this->sourceByToken($source['token']));
        self::assertStringContainsString($source['token'], (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', "/projects/{$this->projectId}/sources");
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(substr($source['token'], 0, 8), (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString($source['token'], (string) $this->client->getResponse()->getContent());
    }

    public function testListShowsThePlatformAndTheLastDayOfActivity(): void
    {
        $this->logIn($this->client, self::ADMIN);
        $this->connectSource('Shipper', 'fluent-bit');

        $crawler = $this->client->request('GET', "/projects/{$this->projectId}/sources");
        self::assertResponseIsSuccessful();
        $row = $crawler->filter('.source-list li')->first()->text();
        self::assertStringContainsString('Shipper', $row);
        self::assertStringContainsString('Fluent Bit', $row);
        self::assertStringContainsString('Brak danych z ostatnich 24 h', $row);

        $crawler = $this->client->request('GET', "/projects/{$this->projectId}/sources?q=nothing");
        self::assertSelectorTextContains('.empty', 'Żadne źródło nie pasuje do „nothing”.');
    }

    public function testAnUnknownPlatformIsRejected(): void
    {
        $this->logIn($this->client, self::ADMIN);
        $crawler = $this->client->request('GET', "/projects/{$this->projectId}/sources/new");
        $token = $crawler->filter('input[name="source[_token]"]')->attr('value');

        $this->client->request('POST', "/projects/{$this->projectId}/sources/new", ['source' => ['name' => 'Bad', 'platform' => 'carrier-pigeon', '_token' => $token]]);
        self::assertResponseStatusCodeSame(422);

        $this->client->submitForm('Utwórz źródło', ['source[name]' => '   ']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testRotationReplacesTheTokenAndRevokeIsFinal(): void
    {
        $this->logIn($this->client, self::ADMIN);
        $source = $this->connectSource();

        $crawler = $this->client->request('GET', "/projects/{$this->projectId}/sources");
        $crawler = $this->client->submit($crawler->selectButton('Wygeneruj token')->form());
        self::assertResponseIsSuccessful();
        $rotated = $crawler->filter('.token-box code')->text();
        self::assertNull($this->sourceByToken($source['token']), 'old token no longer matches');
        self::assertNotNull($this->sourceByToken($rotated));

        $crawler = $this->client->request('GET', "/projects/{$this->projectId}/sources");
        $rotateToken = $crawler->filter('form[action$="/rotate"] input[name="_token"]')->attr('value');
        $this->client->submit($crawler->selectButton('Odwołaj źródło')->form());
        self::assertResponseRedirects("/projects/{$this->projectId}/sources");
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.notice', 'Źródło zostało odwołane.');
        self::assertTrue($this->sourceByToken($rotated)?->isRevoked());
        self::assertSelectorNotExists('.row-menu');

        // The forged request a stale page could still send: refused with the reason, not a crash.
        $this->client->request('POST', "/projects/{$this->projectId}/sources/{$source['id']}/rotate", ['_token' => $rotateToken]);
        self::assertResponseRedirects("/projects/{$this->projectId}/sources");
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert', 'Źródło jest odwołane.');
    }

    public function testDeletingTheProjectRevokesItsSourcesAndQueuesTheLogPurge(): void
    {
        $this->logIn($this->client, self::ADMIN);
        $source = $this->connectSource();

        $crawler = $this->client->request('GET', '/projects');
        $this->client->submit($crawler->selectButton('Usuń projekt')->form());
        self::assertResponseRedirects('/projects');

        self::assertTrue($this->sourceByToken($source['token'])?->isRevoked());

        // Logs go in the background: one purge message queued for the worker.
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        self::assertEquals([new PurgeProjectLogsMessage($this->projectId)], array_map(static fn (Envelope $e): object => $e->getMessage(), $transport->getSent()));
    }

    public function testUnknownProjectOrSourceIsNotFound(): void
    {
        $this->logIn($this->client, self::ADMIN);
        self::getContainer()->get(CreateSource::class)($this->projectId, 'API');
        $crawler = $this->client->request('GET', "/projects/{$this->projectId}/sources");
        $revokeToken = $crawler->filter('form[action$="/revoke"] input[name="_token"]')->attr('value');

        $this->client->request('GET', '/projects/'.self::UNKNOWN.'/sources');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/projects/'.self::UNKNOWN.'/sources/new');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', "/projects/{$this->projectId}/sources/".self::UNKNOWN.'/revoke', ['_token' => $revokeToken]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testManagingSourcesNeedsSourceManage(): void
    {
        $this->logIn($this->client, self::VIEWER);

        $this->client->request('GET', "/projects/{$this->projectId}/sources");
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', "/projects/{$this->projectId}/sources/new");
        self::assertResponseStatusCodeSame(403);
    }

    public function testLogViewersCannotChangeSources(): void
    {
        $created = self::getContainer()->get(CreateSource::class)($this->projectId, 'API');
        $this->logIn($this->client, self::LOG_VIEWER);

        $this->client->request('GET', "/projects/{$this->projectId}/sources");
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', "/projects/{$this->projectId}/sources/new", ['source' => ['name' => 'Sneaky']]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', "/projects/{$this->projectId}/sources/{$created->source->id}/revoke", ['_token' => 'x']);
        self::assertResponseStatusCodeSame(403);
    }
}
