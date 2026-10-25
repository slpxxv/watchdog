<?php

declare(strict_types=1);

namespace Watchdog\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Watchdog\Identity\Application\Command\CreateUser;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Identity\Domain\Role\RoleRepository;
use Watchdog\Logging\Application\Command\CreateSource;
use Watchdog\Logging\Domain\Log\LogEntry;
use Watchdog\Logging\Domain\Log\LogLevel;
use Watchdog\Logging\Domain\Log\LogStore;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\SourceId;
use Watchdog\Project\Application\Command\CreateProject;
use Watchdog\Shared\Domain\Permission;

final class LogPagesTest extends WebTestCase
{
    use SignsIn;

    private KernelBrowser $client;
    private string $projectId;
    private string $apiSource;
    private string $workerSource;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $roles = self::getContainer()->get(RoleRepository::class);
        $roles->save(Role::create($roles->nextIdentity(), 'project_viewer', 'Project viewer', [Permission::ProjectView]));
        $createUser = self::getContainer()->get(CreateUser::class);
        $createUser('root@example.com', self::PASSWORD, [Role::USER, Role::SUPER_ADMIN]);
        $createUser('viewer@example.com', self::PASSWORD, [Role::USER, 'project_viewer']);

        $this->projectId = self::getContainer()->get(CreateProject::class)('Sklep')->value;
        $createSource = self::getContainer()->get(CreateSource::class);
        $this->apiSource = $createSource($this->projectId, 'API')->source->id;
        $this->workerSource = $createSource($this->projectId, 'Worker')->source->id;
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
     * @param array<string, string> $query
     */
    private function get(string $path, array $query = []): Crawler
    {
        return $this->client->request('GET', "/projects/{$this->projectId}/{$path}".([] === $query ? '' : '?'.http_build_query($query)));
    }

    /**
     * @return list<string>
     */
    private static function messages(Crawler $crawler): array
    {
        return $crawler->filter('.log-line .log-message')->each(static fn (Crawler $node): string => $node->text());
    }

    public function testEventsListNewestFirstWithSourceNames(): void
    {
        $this->log('oldest', '-3 minutes');
        $this->log('middle', '-2 minutes', source: $this->workerSource);
        $this->log('newest', '-1 minute');
        $this->logIn($this->client, 'root@example.com');

        $crawler = $this->get('events');

        self::assertResponseIsSuccessful();
        self::assertSame(['newest', 'middle', 'oldest'], self::messages($crawler));
        self::assertSame('Worker', $crawler->filter('.log-line')->eq(1)->filter('.log-source')->text());
        self::assertSame('info', $crawler->filter('.log-line')->first()->filter('.log-level')->text());
    }

    public function testFiltersFromTheQueryString(): void
    {
        $this->log('Payment failed for order 5', level: LogLevel::Error, context: ['order' => ['id' => 5], 'env' => 'prod']);
        $this->log('Payment ok', level: LogLevel::Info);
        $this->log('Worker restarted', level: LogLevel::Warning, source: $this->workerSource);
        $this->logIn($this->client, 'root@example.com');

        self::assertSame(['Worker restarted', 'Payment failed for order 5'], self::messages($this->get('events', ['level' => 'warning'])));
        self::assertSame(['Worker restarted'], self::messages($this->get('events', ['source' => $this->workerSource])));
        self::assertSame(['Payment ok', 'Payment failed for order 5'], self::messages($this->get('events', ['q' => 'payment'])));
    }

    public function testEventsCoverTheLast24HoursUnlessAskedOtherwise(): void
    {
        $this->log('two days old', '-2 days');
        $this->log('fresh');
        $this->logIn($this->client, 'root@example.com');

        self::assertSame(['fresh'], self::messages($this->get('events')));
        self::assertSelectorTextContains('.retention-note', '14 dni');
        self::assertSame(['fresh', 'two days old'], self::messages($this->get('events', ['range' => '7d'])));
    }

    public function testOlderLinesLoadInATurboFrame(): void
    {
        for ($i = 1; $i <= 101; ++$i) {
            $this->log("line {$i}", \sprintf('-%d seconds', 200 - $i));
        }
        $this->logIn($this->client, 'root@example.com');

        $crawler = $this->get('events');
        self::assertCount(100, self::messages($crawler));
        $link = $crawler->filter('turbo-frame .terminal-more a');
        self::assertCount(1, $link);
        $frame = (string) $crawler->filter('turbo-frame')->attr('id');

        $this->client->request('GET', (string) $link->attr('href'), server: ['HTTP_TURBO_FRAME' => $frame]);
        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('<turbo-frame id="'.$frame.'">', $content);
        self::assertStringContainsString('line 1<', $content);
        self::assertStringNotContainsString('<html', $content, 'only the frame, not the whole page');
        self::assertStringNotContainsString('terminal-more', $content, 'no further page');
    }

    public function testLiveTailStartsWithRecentLinesAndTheEndpointFollowsArrivalOrder(): void
    {
        $this->log('first', '-30 seconds');
        $this->log('second', '-20 seconds');
        $this->logIn($this->client, 'root@example.com');

        $crawler = $this->get('live');
        self::assertResponseIsSuccessful();
        self::assertSame(['first', 'second'], self::messages($crawler));
        self::assertStringContainsString('/logs/tail', (string) $crawler->filter('[data-controller~="tail"]')->attr('data-tail-url-value'));

        $this->get('logs/tail');
        self::assertResponseIsSuccessful();
        $lastId = (int) $this->client->getResponse()->headers->get('X-Tail-Last-Id');
        self::assertGreaterThan(0, $lastId);
        self::assertSame(['first', 'second'], $this->tailMessages());

        $this->log('third', '-1 second');
        $this->get('logs/tail', ['after' => (string) $lastId]);
        // "third" is new; the others come back too because they arrived within the overlap window.
        self::assertContains('third', $this->tailMessages());
        self::assertGreaterThan($lastId, (int) $this->client->getResponse()->headers->get('X-Tail-Last-Id'));
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    /**
     * @return list<string>
     */
    private function tailMessages(): array
    {
        $crawler = new Crawler('<ol>'.$this->client->getResponse()->getContent().'</ol>');

        return self::messages($crawler);
    }

    public function testControlCharactersAndMarkupInLogsAreShownAsText(): void
    {
        $this->log("\x1b[31mred\x1b[0m <script>alert(1)</script>");
        $this->logIn($this->client, 'root@example.com');

        $this->get('events');
        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringNotContainsString("\x1b", $html);
        self::assertStringNotContainsString('<script>alert(1)', $html);
        self::assertStringContainsString('red &lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function testInvalidQueriesAre422(): void
    {
        $this->logIn($this->client, 'root@example.com');

        $this->get('events', ['level' => 'loud']);
        self::assertResponseStatusCodeSame(422);
        $this->get('events', ['source' => 'not-a-uuid']);
        self::assertResponseStatusCodeSame(422);
        $this->get('events', ['range' => 'forever']);
        self::assertResponseStatusCodeSame(422);
        $this->get('events', ['cursor' => 'garbage']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.alert', 'Nieprawidłowe zapytanie');
    }

    public function testNeedsLogViewAndAnExistingProject(): void
    {
        $this->logIn($this->client, 'viewer@example.com');
        $this->get('events');
        self::assertResponseStatusCodeSame(403);
        $this->get('live');
        self::assertResponseStatusCodeSame(403);
        $this->get('logs/tail');
        self::assertResponseStatusCodeSame(403);
    }

    public function testUnknownProjectIsNotFound(): void
    {
        $this->logIn($this->client, 'root@example.com');
        $this->client->request('GET', '/projects/01890000-0000-7000-8000-000000000000/events');
        self::assertResponseStatusCodeSame(404);
    }
}
