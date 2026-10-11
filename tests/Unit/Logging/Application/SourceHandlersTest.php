<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Logging\Application;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Watchdog\Logging\Application\Command\CreateSource\CreateSource;
use Watchdog\Logging\Application\Command\CreateSource\CreateSourceHandler;
use Watchdog\Logging\Application\Command\RevokeProjectSources\RevokeProjectSources;
use Watchdog\Logging\Application\Command\RevokeProjectSources\RevokeProjectSourcesHandler;
use Watchdog\Logging\Application\Command\RevokeSource\RevokeSource;
use Watchdog\Logging\Application\Command\RevokeSource\RevokeSourceHandler;
use Watchdog\Logging\Application\Command\RotateSourceToken\RotateSourceToken;
use Watchdog\Logging\Application\Command\RotateSourceToken\RotateSourceTokenHandler;
use Watchdog\Logging\Application\Dto\NewSourceDto;
use Watchdog\Logging\Application\Query\ListSources\ListSources;
use Watchdog\Logging\Application\Query\ListSources\ListSourcesHandler;
use Watchdog\Logging\Domain\Exception\UnknownProject;
use Watchdog\Logging\Domain\Source\Exception\SourceNotFound;
use Watchdog\Logging\Domain\Source\SourceToken;
use Watchdog\Tests\Double\FixedProjectCatalog;
use Watchdog\Tests\Double\InMemorySourceRepository;

final class SourceHandlersTest extends TestCase
{
    private const string PROJECT = '00000000-0000-7000-a000-000000000001';
    private const string OTHER_PROJECT = '00000000-0000-7000-a000-000000000002';
    private const string UNKNOWN = '00000000-0000-7000-c000-999999999999';

    private InMemorySourceRepository $sources;
    private FixedProjectCatalog $projects;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->sources = new InMemorySourceRepository();
        $this->projects = new FixedProjectCatalog([self::PROJECT, self::OTHER_PROJECT]);
        $this->clock = new MockClock('2026-01-01 12:00:00 UTC');
    }

    private function create(string $name = 'API', string $projectId = self::PROJECT): NewSourceDto
    {
        return (new CreateSourceHandler($this->sources, $this->projects, $this->clock))(new CreateSource($projectId, $name));
    }

    public function testCreateReturnsTheTokenOnceAndStoresItsHash(): void
    {
        $created = $this->create();

        self::assertStringStartsWith('wd_', $created->token);
        self::assertSame(substr($created->token, 0, 8), $created->source->tokenPrefix);
        self::assertSame(self::PROJECT, $created->source->projectId);
        self::assertSame('2026-01-01T12:00:00+00:00', $created->source->createdAt);
        self::assertNull($created->source->revokedAt);
        self::assertNotNull($this->sources->ofTokenHash(SourceToken::hashOf($created->token)));
    }

    public function testCreateForUnknownProjectThrows(): void
    {
        $this->expectException(UnknownProject::class);
        $this->create(projectId: self::UNKNOWN);
    }

    public function testRotationInvalidatesTheOldToken(): void
    {
        $created = $this->create();

        $rotated = (new RotateSourceTokenHandler($this->sources))(new RotateSourceToken($created->source->id));

        self::assertNotSame($created->token, $rotated->token);
        self::assertNull($this->sources->ofTokenHash(SourceToken::hashOf($created->token)));
        self::assertNotNull($this->sources->ofTokenHash(SourceToken::hashOf($rotated->token)));
    }

    public function testRevokeMarksTheSource(): void
    {
        $created = $this->create();
        $this->clock->modify('+1 hour');

        (new RevokeSourceHandler($this->sources, $this->clock))(new RevokeSource($created->source->id));

        $list = (new ListSourcesHandler($this->sources, $this->projects))(new ListSources(self::PROJECT));
        self::assertSame('2026-01-01T13:00:00+00:00', $list[0]->revokedAt);
    }

    public function testUnknownSourceThrows(): void
    {
        $this->expectException(SourceNotFound::class);
        (new RevokeSourceHandler($this->sources, $this->clock))(new RevokeSource(self::UNKNOWN));
    }

    public function testListReturnsOnlyTheProjectsSourcesWithoutTokens(): void
    {
        $this->create('API');
        $this->create('Worker');
        $this->create('Other', self::OTHER_PROJECT);

        $list = (new ListSourcesHandler($this->sources, $this->projects))(new ListSources(self::PROJECT));

        self::assertSame(['API', 'Worker'], array_map(static fn ($s): string => $s->name, $list));
        self::assertObjectNotHasProperty('token', $list[0]);
    }

    public function testListForUnknownProjectThrows(): void
    {
        $this->expectException(UnknownProject::class);
        (new ListSourcesHandler($this->sources, $this->projects))(new ListSources(self::UNKNOWN));
    }

    public function testRevokeProjectSourcesLeavesOtherProjectsAlone(): void
    {
        $this->create('API');
        $this->create('Worker');
        $other = $this->create('Other', self::OTHER_PROJECT);

        (new RevokeProjectSourcesHandler($this->sources, $this->clock))(new RevokeProjectSources(self::PROJECT));

        $revoked = array_map(static fn ($s): bool => null !== $s->revokedAt, (new ListSourcesHandler($this->sources, $this->projects))(new ListSources(self::PROJECT)));
        self::assertSame([true, true], $revoked);
        self::assertNotNull($this->sources->ofTokenHash(SourceToken::hashOf($other->token)));
        self::assertNull((new ListSourcesHandler($this->sources, $this->projects))(new ListSources(self::OTHER_PROJECT))[0]->revokedAt);
    }
}
