<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Logging\Application;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Clock\MockClock;
use Watchdog\Logging\Application\Command\PrepareLogPartitions\PrepareLogPartitions;
use Watchdog\Logging\Application\Command\PrepareLogPartitions\PrepareLogPartitionsHandler;
use Watchdog\Logging\Application\Command\PurgeProjectLogs\PurgeProjectLogs;
use Watchdog\Logging\Application\Command\PurgeProjectLogs\PurgeProjectLogsHandler;
use Watchdog\Logging\Application\Port\LogPartitions;
use Watchdog\Logging\Domain\Log\LogCursor;
use Watchdog\Logging\Domain\Log\LogFilter;
use Watchdog\Logging\Domain\Log\LogPage;
use Watchdog\Logging\Domain\Log\LogStore;
use Watchdog\Logging\Domain\ProjectId;

final class LogMaintenanceTest extends TestCase
{
    public function testPreparesSevenDaysEachWayInUtcAndWarnsAboutTheDefaultPartition(): void
    {
        $partitions = new class implements LogPartitions {
            /** @var list<array{string, string}> */
            public array $calls = [];
            public int $stray = 0;

            public function ensureDays(\DateTimeImmutable $from, \DateTimeImmutable $to): array
            {
                $this->calls[] = [$from->format('Y-m-d H:i e'), $to->format('Y-m-d H:i e')];

                return ['log_entry_p20261017'];
            }

            public function linesInDefaultPartition(): int
            {
                return $this->stray;
            }
        };
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $warnings = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->warnings[] = (string) $message;
            }

            public function count(): int
            {
                return \count($this->warnings);
            }
        };
        // 01:30 in Warsaw on the 11th is still the 10th in UTC: days are counted in UTC.
        $handler = new PrepareLogPartitionsHandler($partitions, new MockClock('2026-10-11 01:30:00', 'Europe/Warsaw'), $logger);

        self::assertSame(['log_entry_p20261017'], $handler(new PrepareLogPartitions()));
        self::assertSame([['2026-10-03 00:00 UTC', '2026-10-17 00:00 UTC']], $partitions->calls);
        self::assertSame(0, $logger->count());

        $partitions->stray = 3;
        $handler(new PrepareLogPartitions());
        self::assertSame(1, $logger->count());
    }

    public function testPurgeDeletesInBatchesUntilNothingIsLeft(): void
    {
        $store = new class implements LogStore {
            /** @var list<int> */
            public array $remaining = [PurgeProjectLogsHandler::BATCH_SIZE, PurgeProjectLogsHandler::BATCH_SIZE, 3];
            public int $calls = 0;

            public function append(array $entries): void
            {
            }

            public function search(LogFilter $filter, ?LogCursor $cursor, int $limit): LogPage
            {
                return new LogPage([], null);
            }

            public function tail(LogFilter $filter, int $afterId, \DateTimeImmutable $now, int $limit): array
            {
                return [];
            }

            public function deleteProjectBatch(ProjectId $projectId, int $limit): int
            {
                ++$this->calls;

                return array_shift($this->remaining) ?? 0;
            }
        };

        $deleted = (new PurgeProjectLogsHandler($store))(new PurgeProjectLogs('00000000-0000-7000-a000-000000000001'));

        self::assertSame(2 * PurgeProjectLogsHandler::BATCH_SIZE + 3, $deleted);
        self::assertSame(3, $store->calls, 'stops after the first short batch');
    }
}
