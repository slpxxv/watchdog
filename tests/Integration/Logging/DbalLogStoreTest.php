<?php

declare(strict_types=1);

namespace Watchdog\Tests\Integration\Logging;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Watchdog\Logging\Application\Port\LogPartitions;
use Watchdog\Logging\Domain\Log\LogCursor;
use Watchdog\Logging\Domain\Log\LogEntry;
use Watchdog\Logging\Domain\Log\LogFilter;
use Watchdog\Logging\Domain\Log\LogLevel;
use Watchdog\Logging\Domain\Log\LogStore;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\SourceId;

/**
 * Runs against PostgreSQL in far-future days, so it never collides with real partitions;
 * every test's partitions and rows roll back with its transaction.
 */
final class DbalLogStoreTest extends KernelTestCase
{
    private const string DAY = '2030-01-10';
    private const string NEXT_DAY = '2030-01-11';

    private LogStore $logs;
    private ProjectId $project;
    private ProjectId $otherProject;
    private SourceId $api;
    private SourceId $worker;

    protected function setUp(): void
    {
        $this->logs = self::getContainer()->get(LogStore::class);
        self::getContainer()->get(LogPartitions::class)->ensureDays(new \DateTimeImmutable(self::DAY), new \DateTimeImmutable(self::NEXT_DAY));

        $this->project = ProjectId::fromString('00000000-0000-7000-a000-000000000001');
        $this->otherProject = ProjectId::fromString('00000000-0000-7000-a000-000000000002');
        $this->api = SourceId::fromString('00000000-0000-7000-c000-000000000001');
        $this->worker = SourceId::fromString('00000000-0000-7000-c000-000000000002');
    }

    /**
     * @param array<string, mixed> $context
     */
    private function entry(string $message, string $at = self::DAY.' 12:00:00', LogLevel $level = LogLevel::Info, ?SourceId $source = null, array $context = [], ?ProjectId $project = null, ?string $receivedAt = null): LogEntry
    {
        return new LogEntry(
            projectId: $project ?? $this->project,
            sourceId: $source ?? $this->api,
            timestamp: new \DateTimeImmutable($at.' UTC'),
            receivedAt: new \DateTimeImmutable(($receivedAt ?? $at).' UTC'),
            level: $level,
            message: $message,
            context: $context,
        );
    }

    /**
     * @return list<string>
     */
    private function messages(LogFilter $filter, ?LogCursor $cursor = null, int $limit = 100): array
    {
        return array_map(static fn (LogEntry $e): string => $e->message, $this->logs->search($filter, $cursor, $limit)->entries);
    }

    public function testRoundTripKeepsEveryField(): void
    {
        $this->logs->append([$this->entry('Zażółć gęślą jaźń', self::DAY.' 12:34:56.789012', LogLevel::Error, $this->worker, ['user' => ['id' => 7], 'price' => 1.0])]);

        $stored = $this->logs->search(new LogFilter($this->project), null, 10)->entries;

        self::assertCount(1, $stored);
        self::assertNotNull($stored[0]->id);
        self::assertTrue($stored[0]->sourceId->equals($this->worker));
        self::assertSame(LogLevel::Error, $stored[0]->level);
        self::assertSame('Zażółć gęślą jaźń', $stored[0]->message);
        // assertEquals: jsonb keeps keys sorted its own way, not in insertion order.
        self::assertEquals(['user' => ['id' => 7], 'price' => 1.0], $stored[0]->context);
        self::assertSame(1.0, $stored[0]->context['price']);
        self::assertSame('2030-01-10T12:34:56.789012+00:00', $stored[0]->timestamp->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP'));
    }

    public function testSearchPagesNewestFirstWithoutGapsOrDuplicates(): void
    {
        // Equal timestamps on purpose: the id must break the tie consistently.
        $this->logs->append([
            $this->entry('a', self::DAY.' 10:00:00'),
            $this->entry('b', self::DAY.' 11:00:00'),
            $this->entry('c', self::DAY.' 11:00:00'),
            $this->entry('d', self::DAY.' 11:00:00'),
            $this->entry('e', self::NEXT_DAY.' 09:00:00'),
        ]);
        $filter = new LogFilter($this->project);

        $seen = [];
        $cursor = null;
        do {
            $page = $this->logs->search($filter, $cursor, 2);
            array_push($seen, ...array_map(static fn (LogEntry $e): string => $e->message, $page->entries));
            $cursor = $page->next;
        } while (null !== $cursor);

        self::assertSame(['e', 'd', 'c', 'b', 'a'], $seen);
    }

    public function testFilters(): void
    {
        $this->logs->append([
            $this->entry('debug noise', level: LogLevel::Debug),
            $this->entry('Payment failed: 100% off', self::DAY.' 13:00:00', LogLevel::Error, context: ['order' => ['id' => 5], 'env' => 'prod']),
            $this->entry('worker warning', self::DAY.' 14:00:00', LogLevel::Warning, $this->worker),
            $this->entry('other project error', level: LogLevel::Error, project: $this->otherProject),
            $this->entry('tomorrow', self::NEXT_DAY.' 01:00:00'),
        ]);

        self::assertSame(['worker warning', 'Payment failed: 100% off'], $this->messages(new LogFilter($this->project, minLevel: LogLevel::Warning)));
        self::assertSame(['worker warning'], $this->messages(new LogFilter($this->project, sourceIds: [$this->worker])));
        self::assertSame(['Payment failed: 100% off'], $this->messages(new LogFilter($this->project, text: 'PAYMENT')), 'case-insensitive');
        self::assertSame(['Payment failed: 100% off'], $this->messages(new LogFilter($this->project, text: '100%')), '% is literal');
        self::assertSame([], $this->messages(new LogFilter($this->project, text: '1_0')), '_ is literal');
        self::assertSame(['Payment failed: 100% off'], $this->messages(new LogFilter($this->project, context: ['order' => ['id' => 5]])));
        self::assertSame(['tomorrow'], $this->messages(new LogFilter($this->project, from: new \DateTimeImmutable(self::NEXT_DAY.' UTC'))));
        self::assertSame(['debug noise'], $this->messages(new LogFilter($this->project, to: new \DateTimeImmutable(self::DAY.' 13:00:00 UTC'))), 'to is exclusive');
    }

    public function testTailReturnsArrivalOrderAndRecentLinesForDeduplication(): void
    {
        $now = new \DateTimeImmutable(self::DAY.' 12:00:30 UTC');
        $this->logs->append([
            $this->entry('older than an hour', self::DAY.' 10:00:00', receivedAt: self::DAY.' 12:00:00'),
            $this->entry('seen a while ago', self::DAY.' 11:59:00', receivedAt: self::DAY.' 11:59:00'),
            $this->entry('late commit', self::DAY.' 12:00:25', receivedAt: self::DAY.' 12:00:25'),
            $this->entry('new', self::DAY.' 12:00:28', receivedAt: self::DAY.' 12:00:28'),
        ]);
        $ids = array_column(array_map(static fn (LogEntry $e): array => ['m' => $e->message, 'id' => $e->id], $this->logs->search(new LogFilter($this->project), null, 10)->entries), 'id', 'm');

        // The client last saw "new"; "late commit" has a lower id but arrived within the overlap.
        $tail = $this->logs->tail(new LogFilter($this->project), (int) $ids['new'], $now, 100);
        self::assertSame(['late commit', 'new'], array_map(static fn (LogEntry $e): string => $e->message, $tail));

        // From the start: everything from the last hour, in arrival order; the 10:00 line is outside the window.
        $all = $this->logs->tail(new LogFilter($this->project, minLevel: LogLevel::Info), 0, $now, 100);
        self::assertSame(['seen a while ago', 'late commit', 'new'], array_map(static fn (LogEntry $e): string => $e->message, $all));
    }

    public function testDeleteProjectBatchNeverTouchesOtherPartitionsRows(): void
    {
        // First row of each partition: both sit at ctid (0,1), which is why deletion goes by primary key.
        $this->logs->append([
            $this->entry('doomed', self::DAY.' 08:00:00'),
            $this->entry('survivor', self::NEXT_DAY.' 08:00:00', project: $this->otherProject),
            $this->entry('doomed too', self::NEXT_DAY.' 09:00:00'),
        ]);

        self::assertSame(1, $this->logs->deleteProjectBatch($this->project, 1));
        self::assertSame(1, $this->logs->deleteProjectBatch($this->project, 1));
        self::assertSame(0, $this->logs->deleteProjectBatch($this->project, 1));

        self::assertSame([], $this->messages(new LogFilter($this->project)));
        self::assertSame(['survivor'], $this->messages(new LogFilter($this->otherProject)));
    }

    public function testAppendIsAllOrNothingAcrossBatches(): void
    {
        // 500 good lines make the first INSERT; the 501st has invalid UTF-8, which Postgres rejects.
        $entries = array_map(fn (int $i): LogEntry => $this->entry("line {$i}"), range(1, 500));
        $entries[] = $this->entry("broken \xC3\x28 utf-8");

        try {
            $this->logs->append($entries);
            self::fail('Expected the second batch to fail.');
        } catch (\Doctrine\DBAL\Exception) {
        }

        self::assertSame([], $this->messages(new LogFilter($this->project)));
    }

    public function testPartitionManagerIsIdempotentAndSurvivesAConflict(): void
    {
        $partitions = self::getContainer()->get(LogPartitions::class);
        self::assertSame([], $partitions->ensureDays(new \DateTimeImmutable(self::DAY), new \DateTimeImmutable(self::NEXT_DAY)), 'already there');

        // A line for a day without a partition lands in the default one...
        $this->logs->append([$this->entry('stray', '2031-05-05 12:00:00')]);
        self::assertSame(1, $partitions->linesInDefaultPartition());

        // ...after which Postgres refuses that day's partition: logged and skipped, the next day still made.
        self::assertSame(['log_entry_p20310506'], $partitions->ensureDays(new \DateTimeImmutable('2031-05-05'), new \DateTimeImmutable('2031-05-06')));
        self::assertEquals(1, self::getContainer()->get(Connection::class)->fetchOne('SELECT count(*) FROM log_entry_default'), 'connection still usable');
    }
}
