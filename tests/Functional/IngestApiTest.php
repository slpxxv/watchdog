<?php

declare(strict_types=1);

namespace Watchdog\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Watchdog\Identity\Application\Command\CreateUser\CreateUser;
use Watchdog\Identity\Application\Command\CreateUser\CreateUserHandler;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Logging\Application\Command\CreateSource\CreateSource;
use Watchdog\Logging\Application\Command\CreateSource\CreateSourceHandler;
use Watchdog\Logging\Application\Command\RevokeSource\RevokeSource;
use Watchdog\Logging\Application\Command\RevokeSource\RevokeSourceHandler;
use Watchdog\Logging\Domain\Log\LogEntry;
use Watchdog\Logging\Domain\Log\LogFilter;
use Watchdog\Logging\Domain\Log\LogLevel;
use Watchdog\Logging\Domain\Log\LogStore;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Project\Application\Command\CreateProject\CreateProject;
use Watchdog\Project\Application\Command\CreateProject\CreateProjectHandler;

final class IngestApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $projectId;
    private string $sourceId;
    private string $token;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // Keep one kernel: the rate limiter's test storage lives in memory.
        $this->client->disableReboot();

        $this->projectId = self::getContainer()->get(CreateProjectHandler::class)(new CreateProject('Sklep'))->value;
        $created = self::getContainer()->get(CreateSourceHandler::class)(new CreateSource($this->projectId, 'API'));
        $this->sourceId = $created->source->id;
        $this->token = $created->token;
    }

    /**
     * @param array<string, string> $headers
     */
    private function ingest(string $body, array $headers = [], ?string $token = null): void
    {
        $token ??= $this->token;
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ('' !== $token) { // an empty token means: send no Authorization header
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $this->client->request('POST', '/api/v1/ingest/logs', server: $server, content: $body);
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

    /**
     * @return list<LogEntry>
     */
    private function stored(): array
    {
        return self::getContainer()->get(LogStore::class)->search(new LogFilter(ProjectId::fromString($this->projectId)), null, 100)->entries;
    }

    public function testAcceptsAJsonBatchAndStoresIt(): void
    {
        $this->ingest('[{"level":"error","message":"Payment failed","context":{"order":5}},{"message":"second"}]');

        self::assertResponseStatusCodeSame(202);
        self::assertSame(['accepted' => 2, 'normalized' => 0], $this->json());

        $stored = $this->stored();
        self::assertCount(2, $stored);
        $byMessage = array_column(array_map(static fn (LogEntry $e): array => ['m' => $e->message, 'e' => $e], $stored), 'e', 'm');
        self::assertSame(LogLevel::Error, $byMessage['Payment failed']->level);
        self::assertSame(['order' => 5], $byMessage['Payment failed']->context);
        self::assertSame($this->sourceId, $byMessage['second']->sourceId->value);
    }

    public function testAcceptsGzippedNdjsonAndReportsNormalizedLines(): void
    {
        $body = (string) gzencode("{\"log\":\"from fluent bit\",\"level\":\"warn\"}\n{\"message\":\"odd\",\"level\":\"LOUD\",\"timestamp\":\"not a date\"}\n");

        $this->ingest($body, ['Content-Encoding' => 'gzip', 'Content-Type' => 'application/x-ndjson']);

        self::assertResponseStatusCodeSame(202);
        self::assertSame(['accepted' => 2, 'normalized' => 1], $this->json());
        $odd = array_values(array_filter($this->stored(), static fn (LogEntry $e): bool => 'odd' === $e->message))[0];
        self::assertSame(['_original_level' => 'LOUD', '_original_timestamp' => 'not a date'], $odd->context);
    }

    public function testRejectsBadBodiesWithProblemDetails(): void
    {
        $this->ingest('[{"message":');
        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $this->ingest('["just a string"]');
        self::assertResponseStatusCodeSame(422);

        $this->ingest('['.implode(',', array_fill(0, 1001, '{}')).']');
        self::assertResponseStatusCodeSame(413);

        self::assertSame([], $this->stored());
    }

    public function testNeedsAValidLiveSourceToken(): void
    {
        $this->ingest('{"message":"x"}', token: '');
        self::assertResponseStatusCodeSame(401);

        $this->ingest('{"message":"x"}', token: 'wd_made-up');
        self::assertResponseStatusCodeSame(401);

        self::getContainer()->get(RevokeSourceHandler::class)(new RevokeSource($this->sourceId));
        $this->ingest('{"message":"x"}');
        self::assertResponseStatusCodeSame(401);

        self::assertSame([], $this->stored());
    }

    public function testASessionDoesNotOpenTheIngestEndpoint(): void
    {
        self::getContainer()->get(CreateUserHandler::class)(new CreateUser('root@example.com', 'correct horse battery staple', [Role::USER, Role::SUPER_ADMIN]));
        $this->client->jsonRequest('POST', '/api/login', ['email' => 'root@example.com', 'password' => 'correct horse battery staple']);
        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/api/v1/ingest/logs', server: ['CONTENT_TYPE' => 'application/json'], content: '{"message":"x"}');
        self::assertResponseStatusCodeSame(401);
    }

    public function testRateLimitPerSource(): void
    {
        // Test config: a bucket of 3.
        for ($i = 0; $i < 3; ++$i) {
            $this->ingest('{"message":"ok"}');
            self::assertResponseStatusCodeSame(202);
        }

        $this->ingest('{"message":"one too many"}');
        self::assertResponseStatusCodeSame(429);
        self::assertGreaterThan(0, (int) $this->client->getResponse()->headers->get('Retry-After'));

        // Another source has its own bucket.
        $other = self::getContainer()->get(CreateSourceHandler::class)(new CreateSource($this->projectId, 'Worker'));
        $this->ingest('{"message":"other source"}', token: $other->token);
        self::assertResponseStatusCodeSame(202);
    }
}
