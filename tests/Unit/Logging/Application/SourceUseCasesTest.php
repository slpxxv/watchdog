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
use Watchdog\Logging\Application\Dto\SourceActivityDto;
use Watchdog\Logging\Application\Query\ListSources;
use Watchdog\Logging\Domain\Exception\UnknownProject;
use Watchdog\Logging\Domain\Log\SourceVolume;
use Watchdog\Logging\Domain\Source\Exception\SourceNotFound;
use Watchdog\Logging\Domain\Source\Platform;
use Watchdog\Logging\Domain\Source\SourceId;
use Watchdog\Logging\Domain\Source\SourceToken;
use Watchdog\Tests\Double\FixedProjectCatalog;
use Watchdog\Tests\Double\InMemorySourceRepository;
use Watchdog\Tests\Double\SpyLogStore;

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

    private function create(string $name = 'API', string $projectId = self::PROJECT, Platform $platform = Platform::Http): NewSourceDto
    {
        return (new CreateSource($this->sources, $this->projects, $this->clock))($projectId, $name, $platform);
    }

    private function listSources(?SpyLogStore $logs = null): ListSources
    {
        return new ListSources($this->sources, $this->projects, $logs ?? new SpyLogStore(), $this->clock);
    }

    public function testListShowsThePlatformAndTheLastDayOfActivityPerHour(): void
    {
        $vector = $this->create('Vector', platform: Platform::Vector)->source->id;
        $this->create('Quiet');
        $this->clock->modify('2026-01-01 12:40:00 UTC');
        $logs = new SpyLogStore();
        $logs->volumes = [
            new SourceVolume(SourceId::fromString($vector), new \DateTimeImmutable('2025-12-31 13:00 UTC'), 4, new \DateTimeImmutable('2025-12-31 13:59 UTC')),
            new SourceVolume(SourceId::fromString($vector), new \DateTimeImmutable('2026-01-01 12:00 UTC'), 2, new \DateTimeImmutable('2026-01-01 12:31 UTC')),
        ];

        [$first, $quiet] = $this->listSources($logs)(self::PROJECT);

        self::assertEquals(new \DateTimeImmutable('2025-12-31 13:00 UTC'), $logs->lastSince, '24 hours, the current one included');
        self::assertSame('vector', $first->platform);
        self::assertNotNull($first->activity);
        self::assertSame(6, $first->activity->lines);
        self::assertSame('2026-01-01T12:31:00+00:00', $first->activity->lastLogAt);
        self::assertSame([4, ...array_fill(0, 22, 0), 2], $first->activity->hourly);
        self::assertSame('http', $quiet->platform);
        self::assertEquals(new SourceActivityDto(0, null, array_fill(0, 24, 0)), $quiet->activity);
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

        $list = $this->listSources()(self::PROJECT);
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

        $list = $this->listSources()(self::PROJECT);

        self::assertSame(['API', 'Worker'], array_map(static fn ($s): string => $s->name, $list));
        self::assertObjectNotHasProperty('token', $list[0]);
    }

    public function testListForUnknownProjectThrows(): void
    {
        $this->expectException(UnknownProject::class);
        $this->listSources()(self::UNKNOWN);
    }

    public function testRevokeProjectSourcesLeavesOtherProjectsAlone(): void
    {
        $this->create('API');
        $this->create('Worker');
        $other = $this->create('Other', self::OTHER_PROJECT);

        (new RevokeProjectSources($this->sources, $this->clock))(self::PROJECT);

        $revoked = array_map(static fn ($s): bool => null !== $s->revokedAt, $this->listSources()(self::PROJECT));
        self::assertSame([true, true], $revoked);
        self::assertNotNull($this->sources->ofTokenHash(SourceToken::hashOf($other->token)));
        self::assertNull($this->listSources()(self::OTHER_PROJECT)[0]->revokedAt);
    }
}
