<?php

declare(strict_types=1);

namespace Watchdog\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Watchdog\Identity\Application\Command\CreateUser\CreateUser;
use Watchdog\Identity\Application\Command\CreateUser\CreateUserHandler;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Identity\Domain\Role\RoleRepository;
use Watchdog\Logging\Application\Command\CreateSource\CreateSource;
use Watchdog\Logging\Application\Command\CreateSource\CreateSourceHandler;
use Watchdog\Logging\Domain\Log\LogEntry;
use Watchdog\Logging\Domain\Log\LogLevel;
use Watchdog\Logging\Domain\Log\LogStore;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\SourceId;
use Watchdog\Project\Application\Command\CreateProject\CreateProject;
use Watchdog\Project\Application\Command\CreateProject\CreateProjectHandler;
use Watchdog\Shared\Domain\Permission;

final class LogApiTest extends WebTestCase
{
    private const string PASSWORD = 'correct horse battery staple';

    private KernelBrowser $client;
    private string $projectId;
    private string $apiSource;
    private string $workerSource;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $roles = self::getContainer()->get(RoleRepository::class);
        $roles->save(Role::create($roles->nextIdentity(), 'project_viewer', 'Project viewer', [Permission::ProjectView]));
        $createUser = self::getContainer()->get(CreateUserHandler::class);
        $createUser(new CreateUser('root@example.com', self::PASSWORD, [Role::USER, Role::SUPER_ADMIN]));
        $createUser(new CreateUser('viewer@example.com', self::PASSWORD, [Role::USER, 'project_viewer']));

        $this->projectId = self::getContainer()->get(CreateProjectHandler::class)(new CreateProject('Sklep'))->value;
        $createSource = self::getContainer()->get(CreateSourceHandler::class);
        $this->apiSource = $createSource(new CreateSource($this->projectId, 'API'))->source->id;
        $this->workerSource = $createSource(new CreateSource($this->projectId, 'Worker'))->source->id;
    }

    private function logIn(string $email = 'root@example.com'): void
    {
        $this->client->jsonRequest('POST', '/api/login', ['email' => $email, 'password' => self::PASSWORD]);
        self::assertResponseIsSuccessful();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $message, string $ago = '-1 minute', LogLevel $level = LogLevel::Info, ?string $source = null, array $context = []): void
    {
        $now = new \DateTimeImmutable();
        self::getContainer()->get(LogStore::class)->append([new LogEntry(
            ProjectId::fromString($this->projectId),
            SourceId::fromString($source ?? $this->apiSource),
            $now->modify($ago),
            $now,
            $level,
            $message,
            $context,
        )]);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $this->client->request('GET', "/api/projects/{$this->projectId}/{$path}".([] === $query ? '' : '?'.http_build_query($query)), server: ['HTTP_ACCEPT' => 'application/json']);

        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @param array<mixed> $page
     *
     * @return list<mixed>
     */
    private static function messages(array $page): array
    {
        self::assertIsArray($page['items']);

        return array_column($page['items'], 'message');
    }

    public function testSearchPagesNewestFirstWithSourceNames(): void
    {
        $this->log('oldest', '-3 minutes');
        $this->log('middle', '-2 minutes', source: $this->workerSource);
        $this->log('newest', '-1 minute');
        $this->logIn();

        $first = $this->get('logs', ['limit' => 2]);
        self::assertResponseIsSuccessful();
        self::assertSame(['newest', 'middle'], self::messages($first));
        $items = $first['items'];
        self::assertIsArray($items);
        [$newest, $middle] = $items;
        self::assertIsArray($newest);
        self::assertIsArray($middle);
        self::assertSame('Worker', $middle['sourceName']);
        self::assertSame('info', $newest['level']);
        self::assertIsString($first['nextCursor']);

        $second = $this->get('logs', ['limit' => 2, 'cursor' => $first['nextCursor']]);
        self::assertSame(['oldest'], self::messages($second));
        self::assertNull($second['nextCursor']);
    }

    public function testFiltersFromTheQueryString(): void
    {
        $this->log('Payment failed for order 5', level: LogLevel::Error, context: ['order' => ['id' => 5], 'env' => 'prod']);
        $this->log('Payment ok', level: LogLevel::Info);
        $this->log('Worker restarted', level: LogLevel::Warning, source: $this->workerSource);
        $this->logIn();

        self::assertSame(['Worker restarted', 'Payment failed for order 5'], self::messages($this->get('logs', ['level' => 'warning'])));
        self::assertSame(['Worker restarted'], self::messages($this->get('logs', ['sources' => [$this->workerSource]])));
        self::assertSame(['Payment ok', 'Payment failed for order 5'], self::messages($this->get('logs', ['q' => 'payment'])));
        self::assertSame(['Payment failed for order 5'], self::messages($this->get('logs', ['context' => '{"order":{"id":5}}'])));
    }

    public function testSearchCoversTheLast24HoursUnlessAskedOtherwise(): void
    {
        $this->log('two days old', '-2 days');
        $this->log('fresh');
        $this->logIn();

        $default = $this->get('logs');
        self::assertSame(['fresh'], self::messages($default));
        self::assertSame(14, $default['retentionDays']);
        self::assertIsString($default['from']);

        $wider = $this->get('logs', ['from' => (new \DateTimeImmutable('-3 days'))->format(\DATE_ATOM)]);
        self::assertSame(['fresh', 'two days old'], self::messages($wider));
    }

    public function testTailFollowsArrivalOrder(): void
    {
        $this->log('first', '-30 seconds');
        $this->log('second', '-20 seconds');
        $this->logIn();

        $tail = $this->get('logs/tail');
        self::assertResponseIsSuccessful();
        self::assertSame(['first', 'second'], self::messages($tail));
        self::assertIsInt($tail['lastId']);

        $this->log('third', '-1 second');
        $next = $this->get('logs/tail', ['after' => $tail['lastId']]);
        // "third" is new; the others come back too because they arrived within the overlap window.
        self::assertContains('third', self::messages($next));
        self::assertGreaterThan($tail['lastId'], $next['lastId']);
    }

    public function testInvalidQueriesAre422(): void
    {
        $this->logIn();

        $this->get('logs', ['level' => 'loud']);
        self::assertResponseStatusCodeSame(422);
        $this->get('logs', ['limit' => 500]);
        self::assertResponseStatusCodeSame(422);
        $this->get('logs', ['cursor' => 'garbage']);
        self::assertResponseStatusCodeSame(422);
        $this->get('logs', ['from' => 'not a date']);
        self::assertResponseStatusCodeSame(422);
        $body = $this->get('logs', ['context' => '{oops']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('log.query.invalid', $body['messageKey']);
    }

    public function testNeedsLogViewAndAnExistingProject(): void
    {
        $this->logIn('viewer@example.com');
        $this->get('logs');
        self::assertResponseStatusCodeSame(403);
        $this->get('logs/tail');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/api/logout');

        $this->logIn();
        $this->client->request('GET', '/api/projects/01890000-0000-7000-8000-000000000000/logs');
        self::assertResponseStatusCodeSame(404);
    }
}
