<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Logging\Domain;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Watchdog\Logging\Domain\Log\LogCursor;
use Watchdog\Logging\Domain\Log\LogFilter;
use Watchdog\Logging\Domain\Log\LogLevel;
use Watchdog\Logging\Domain\ProjectId;

final class LogValuesTest extends TestCase
{
    public function testCursorRoundTripsWithMicroseconds(): void
    {
        $cursor = new LogCursor(new \DateTimeImmutable('2026-10-10 12:34:56.789012+00:00'), 42);

        $decoded = LogCursor::decode($cursor->encode());

        self::assertSame('2026-10-10T12:34:56.789012+00:00', $decoded->timestamp->format('Y-m-d\TH:i:s.uP'));
        self::assertSame(42, $decoded->id);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $cursor->encode(), 'URL-safe');
    }

    #[TestWith([''])]
    #[TestWith(['not a cursor'])]
    #[TestWith(['MjAyNi0xMC0xMHwxMg'])] // "2026-10-10|12": wrong date format
    #[TestWith(['MjAyNi0xMC0xMFQxMjozNDo1Ni43ODkwMTIrMDA6MDB8eA'])] // id "x"
    public function testCursorRejectsAnythingElse(string $encoded): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LogCursor::decode($encoded);
    }

    public function testFilterRejectsAnEmptyTimeRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LogFilter(
            ProjectId::fromString('00000000-0000-7000-a000-000000000001'),
            from: new \DateTimeImmutable('2026-10-10'),
            to: new \DateTimeImmutable('2026-10-10'),
        );
    }

    public function testLevelsAreLabelledLikePsr3InSeverityOrder(): void
    {
        self::assertSame(['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'], array_map(static fn (LogLevel $l): string => $l->label(), LogLevel::cases()));
    }
}
