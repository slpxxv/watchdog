<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Logging\Application;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Watchdog\Logging\Application\Query\LogEntryPresenter;
use Watchdog\Logging\Application\Query\SearchLogs\LogQueryFilters;
use Watchdog\Logging\Application\Query\SearchLogs\SearchLogs;
use Watchdog\Logging\Application\Query\SearchLogs\SearchLogsHandler;
use Watchdog\Logging\Application\Query\TailLogs\TailLogs;
use Watchdog\Logging\Application\Query\TailLogs\TailLogsHandler;
use Watchdog\Logging\Domain\Exception\UnknownProject;
use Watchdog\Logging\Domain\Log\Exception\InvalidLogQuery;
use Watchdog\Logging\Domain\Log\LogCursor;
use Watchdog\Logging\Domain\Log\LogEntry;
use Watchdog\Logging\Domain\Log\LogLevel;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\Source;
use Watchdog\Logging\Domain\Source\SourceId;
use Watchdog\Logging\Domain\Source\SourceToken;
use Watchdog\Tests\Double\FixedProjectCatalog;
use Watchdog\Tests\Double\InMemorySourceRepository;
use Watchdog\Tests\Double\SpyLogStore;

final class LogQueriesTest extends TestCase
{
    private const string PROJECT = '00000000-0000-7000-a000-000000000001';
    private const string SOURCE = '00000000-0000-7000-c000-000000000001';

    private SpyLogStore $logs;
    private SearchLogsHandler $search;
    private TailLogsHandler $tail;

    protected function setUp(): void
    {
        $this->logs = new SpyLogStore();
        $sources = new InMemorySourceRepository();
        $sources->save(Source::create(SourceId::fromString(self::SOURCE), ProjectId::fromString(self::PROJECT), 'API', SourceToken::generate(), new \DateTimeImmutable()));
        $projects = new FixedProjectCatalog([self::PROJECT]);
        $clock = new MockClock('2026-10-11 12:00:00 UTC');
        $presenter = new LogEntryPresenter($sources);

        $this->search = new SearchLogsHandler($this->logs, $projects, $presenter, $clock);
        $this->tail = new TailLogsHandler($this->logs, $projects, $presenter, $clock);
    }

    private function entry(int $id, string $sourceId = self::SOURCE): LogEntry
    {
        return new LogEntry(
            ProjectId::fromString(self::PROJECT),
            SourceId::fromString($sourceId),
            new \DateTimeImmutable('2026-10-11 11:59:00.123 UTC'),
            new \DateTimeImmutable('2026-10-11 11:59:01 UTC'),
            LogLevel::Warning,
            "line {$id}",
            ['k' => 'v'],
            $id,
        );
    }

    public function testSearchDefaultsToTheLast24Hours(): void
    {
        $page = ($this->search)(new SearchLogs(self::PROJECT));

        self::assertEquals(new \DateTimeImmutable('2026-10-10 12:00:00 UTC'), $this->logs->lastFilter?->from);
        self::assertNull($this->logs->lastFilter?->to);
        self::assertSame('2026-10-10T12:00:00+00:00', $page->from);
        self::assertSame(SearchLogs::DEFAULT_LIMIT, $this->logs->lastLimit);
    }

    public function testAnUpperBoundAloneStillGetsA24HourWindow(): void
    {
        ($this->search)(new SearchLogs(self::PROJECT, to: new \DateTimeImmutable('2026-10-01 00:00 UTC')));

        self::assertEquals(new \DateTimeImmutable('2026-09-30 00:00 UTC'), $this->logs->lastFilter?->from);
    }

    public function testSearchPassesFiltersAndCursorThrough(): void
    {
        $cursor = new LogCursor(new \DateTimeImmutable('2026-10-11 11:00 UTC'), 7);

        ($this->search)(new SearchLogs(
            self::PROJECT,
            new LogQueryFilters('warning', [self::SOURCE], '  timeout  ', '{"env":"prod"}'),
            cursor: $cursor->encode(),
            limit: 50,
        ));

        $filter = $this->logs->lastFilter;
        self::assertNotNull($filter);
        self::assertSame(LogLevel::Warning, $filter->minLevel);
        self::assertSame(self::SOURCE, $filter->sourceIds[0]->value);
        self::assertSame('timeout', $filter->text, 'trimmed');
        self::assertSame(['env' => 'prod'], $filter->context);
        self::assertSame(7, $this->logs->lastCursor?->id);
        self::assertSame(50, $this->logs->lastLimit);
    }

    public function testSearchShowsSourceNamesAndTheNextCursor(): void
    {
        $this->logs->entries = [$this->entry(2), $this->entry(1, '00000000-0000-7000-c000-000000000099')];
        $this->logs->next = new LogCursor(new \DateTimeImmutable('2026-10-11 11:00 UTC'), 1);

        $page = ($this->search)(new SearchLogs(self::PROJECT));

        self::assertSame('API', $page->items[0]->sourceName);
        self::assertNull($page->items[1]->sourceName, 'source from elsewhere: no name, no failure');
        self::assertSame('warning', $page->items[0]->level);
        self::assertSame('2026-10-11T11:59:00.123+00:00', $page->items[0]->timestamp);
        self::assertSame($this->logs->next->encode(), $page->nextCursor);
    }

    /**
     * @return iterable<string, array{SearchLogs}>
     */
    public static function invalidSearches(): iterable
    {
        yield 'unknown level' => [new SearchLogs(self::PROJECT, new LogQueryFilters('loud'))];
        yield 'source not a uuid' => [new SearchLogs(self::PROJECT, new LogQueryFilters(sourceIds: ['nope']))];
        yield 'context not json' => [new SearchLogs(self::PROJECT, new LogQueryFilters(context: '{oops'))];
        yield 'context a list' => [new SearchLogs(self::PROJECT, new LogQueryFilters(context: '[1,2]'))];
        yield 'bad cursor' => [new SearchLogs(self::PROJECT, cursor: 'garbage')];
        yield 'limit too high' => [new SearchLogs(self::PROJECT, limit: 201)];
        yield 'limit zero' => [new SearchLogs(self::PROJECT, limit: 0)];
        yield 'empty range' => [new SearchLogs(self::PROJECT, from: new \DateTimeImmutable('2026-10-11'), to: new \DateTimeImmutable('2026-10-10'))];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSearches')]
    public function testInvalidSearchesAreRejectedNotGuessed(SearchLogs $query): void
    {
        $this->expectException(InvalidLogQuery::class);
        ($this->search)($query);
    }

    public function testUnknownProject(): void
    {
        $this->expectException(UnknownProject::class);
        ($this->search)(new SearchLogs('00000000-0000-7000-a000-000000000099'));
    }

    public function testTailReportsTheHighestIdSeen(): void
    {
        $this->logs->entries = [$this->entry(5), $this->entry(9), $this->entry(7)];

        $tail = ($this->tail)(new TailLogs(self::PROJECT, afterId: 4, filters: new LogQueryFilters('error')));

        self::assertSame(9, $tail->lastId);
        self::assertSame(['line 5', 'line 9', 'line 7'], array_map(static fn ($e): string => $e->message, $tail->items));
        self::assertSame(4, $this->logs->lastAfterId);
        $filter = $this->logs->lastFilter;
        self::assertNotNull($filter);
        self::assertSame(LogLevel::Error, $filter->minLevel);
        self::assertNull($filter->from, 'the store applies the tail window itself');
    }

    public function testEmptyTailKeepsTheCursor(): void
    {
        self::assertSame(42, ($this->tail)(new TailLogs(self::PROJECT, afterId: 42))->lastId);
    }

    public function testTailRejectsANegativeCursorOrLimitOverMax(): void
    {
        foreach ([new TailLogs(self::PROJECT, afterId: -1), new TailLogs(self::PROJECT, limit: 501)] as $query) {
            try {
                ($this->tail)($query);
                self::fail('Expected InvalidLogQuery.');
            } catch (InvalidLogQuery) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
