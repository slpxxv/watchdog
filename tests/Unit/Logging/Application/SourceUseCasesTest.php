<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Logging\Application;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Watchdog\Logging\Application\Command\CreateSource;
use Watchdog\Logging\Application\Command\RevokeProjectSources;
use Watchdog\Logging\Application\Command\RevokeSource;
use Watchdog\Logging\Application\Command\RotateSourceToken;
use Watchdog\Logging\Application\Dto\NewSourceDto;
use Watchdog\Logging\Application\Query\ListSources;
use Watchdog\Logging\Domain\Exception\UnknownProject;
use Watchdog\Logging\Domain\Source\Exception\SourceNotFound;
use Watchdog\Logging\Domain\Source\SourceToken;
use Watchdog\Tests\Double\FixedProjectCatalog;
use Watchdog\Tests\Double\InMemorySourceRepository;

final class SourceUseCasesTest extends TestCase
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
        return (new CreateSource($this->sources, $this->projects, $this->clock))($projectId, $name);
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

        $rotated = (new RotateSourceToken($this->sources))($created->source->id);

        self::assertNotSame($created->token, $rotated->token);
        self::assertNull($this->sources->ofTokenHash(SourceToken::hashOf($created->token)));
        self::assertNotNull($this->sources->ofTokenHash(SourceToken::hashOf($rotated->token)));
    }

    public function testRevokeMarksTheSource(): void
    {
        $created = $this->create();
        $this->clock->modify('+1 hour');

        (new RevokeSource($this->sources, $this->clock))($created->source->id);

        $list = (new ListSources($this->sources, $this->projects))(self::PROJECT);
        self::assertSame('2026-01-01T13:00:00+00:00', $list[0]->revokedAt);
    }

    public function testUnknownSourceThrows(): void
    {
        $this->expectException(SourceNotFound::class);
        (new RevokeSource($this->sources, $this->clock))(self::UNKNOWN);
    }

    public function testListReturnsOnlyTheProjectsSourcesWithoutTokens(): void
    {
        $this->create('API');
        $this->create('Worker');
        $this->create('Other', self::OTHER_PROJECT);

        $list = (new ListSources($this->sources, $this->projects))(self::PROJECT);

        self::assertSame(['API', 'Worker'], array_map(static fn ($s): string => $s->name, $list));
        self::assertObjectNotHasProperty('token', $list[0]);
    }

    public function testListForUnknownProjectThrows(): void
    {
        $this->expectException(UnknownProject::class);
        (new ListSources($this->sources, $this->projects))(self::UNKNOWN);
    }

    public function testRevokeProjectSourcesLeavesOtherProjectsAlone(): void
    {
        $this->create('API');
        $this->create('Worker');
        $other = $this->create('Other', self::OTHER_PROJECT);

        (new RevokeProjectSources($this->sources, $this->clock))(self::PROJECT);

        $revoked = array_map(static fn ($s): bool => null !== $s->revokedAt, (new ListSources($this->sources, $this->projects))(self::PROJECT));
        self::assertSame([true, true], $revoked);
        self::assertNotNull($this->sources->ofTokenHash(SourceToken::hashOf($other->token)));
        self::assertNull((new ListSources($this->sources, $this->projects))(self::OTHER_PROJECT)[0]->revokedAt);
    }
}
