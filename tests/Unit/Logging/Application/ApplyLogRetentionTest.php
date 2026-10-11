<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Logging\Application;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Watchdog\Logging\Application\Command\ApplyLogRetention\ApplyLogRetention;
use Watchdog\Logging\Application\Command\ApplyLogRetention\ApplyLogRetentionHandler;
use Watchdog\Logging\Application\Command\IngestLogs\LineNormalizer;
use Watchdog\Logging\Application\Port\LogPartitions;
use Watchdog\Tests\Double\FixedRetention;

final class ApplyLogRetentionTest extends TestCase
{
    private function partitions(): LogPartitions
    {
        return new class implements LogPartitions {
            public ?string $oldestKept = null;

            public function ensureDays(\DateTimeImmutable $from, \DateTimeImmutable $to): array
            {
                return [];
            }

            public function linesInDefaultPartition(): int
            {
                return 0;
            }

            public function dropOlderThan(\DateTimeImmutable $oldestKept): array
            {
                $this->oldestKept = $oldestKept->format('Y-m-d H:i e');

                return ['log_entry_p20260927'];
            }
        };
    }

    public function testKeepsTodayPlusTheDaysBeforeItInUtc(): void
    {
        $partitions = $this->partitions();
        // 01:30 in Warsaw on the 12th is still the 11th in UTC.
        $handler = new ApplyLogRetentionHandler($partitions, new FixedRetention(14), new MockClock('2026-10-12 01:30:00', 'Europe/Warsaw'));

        self::assertSame(['log_entry_p20260927'], $handler(new ApplyLogRetention()));
        self::assertSame('2026-09-28 00:00 UTC', $partitions->oldestKept ?? null, '11 Oct and the 13 days before it');
    }

    public function testRefusesARetentionShorterThanTheIngestWindow(): void
    {
        $partitions = $this->partitions();
        $handler = new ApplyLogRetentionHandler($partitions, new FixedRetention(ApplyLogRetention::MIN_DAYS - 1), new MockClock());

        try {
            $handler(new ApplyLogRetention());
            self::fail('Expected a LogicException.');
        } catch (\LogicException $e) {
            self::assertStringContainsString('LOG_RETENTION_DAYS', $e->getMessage());
        }
        self::assertNull($partitions->oldestKept ?? null, 'nothing dropped');
    }

    public function testMinimumOutlastsTheIngestWindow(): void
    {
        $now = new \DateTimeImmutable('2026-10-11 12:00 UTC');
        $windowDays = (int) $now->modify(LineNormalizer::WINDOW_PAST)->diff($now)->days;

        self::assertGreaterThan($windowDays, ApplyLogRetention::MIN_DAYS);
    }
}
