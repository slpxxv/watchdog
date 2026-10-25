<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Logging\UI;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Watchdog\Logging\Application\Dto\SourceActivityDto;
use Watchdog\Logging\Application\Dto\SourceDto;
use Watchdog\Logging\UI\Twig\SourcePresenter;

final class SourcePresenterTest extends TestCase
{
    private function source(?SourceActivityDto $activity, ?string $revokedAt = null): SourceDto
    {
        return new SourceDto('id', 'project', 'API', 'wd_abcde', '2026-10-01T00:00:00+00:00', $revokedAt, 'http', $activity);
    }

    private function presenter(): SourcePresenter
    {
        return new SourcePresenter(new MockClock('2026-10-11 12:00:00 UTC'));
    }

    public function testStatusSaysHowLongAgoTheLastLogWas(): void
    {
        $status = fn (string $last): string => $this->presenter()->status($this->source(new SourceActivityDto(5, $last, array_fill(0, 24, 0))));

        self::assertSame('Ostatni log przed chwilą', $status('2026-10-11T11:59:30+00:00'));
        self::assertSame('Ostatni log 5 min temu', $status('2026-10-11T11:55:00+00:00'));
        self::assertSame('Ostatni log 3 godz. temu', $status('2026-10-11T09:00:00+00:00'));
    }

    public function testStatusOfSilentAndRevokedSources(): void
    {
        self::assertSame('Brak danych z ostatnich 24 h', $this->presenter()->status($this->source(new SourceActivityDto(0, null, array_fill(0, 24, 0)))));
        self::assertSame('Odwołane', $this->presenter()->status($this->source(null, '2026-10-02T00:00:00+00:00')));
    }

    public function testBarsScaleToThePeakAndKeepABaseline(): void
    {
        $hourly = array_fill(0, 24, 0);
        $hourly[23] = 50;
        $hourly[22] = 1;

        $bars = $this->presenter()->bars($this->source(new SourceActivityDto(51, null, $hourly)));

        self::assertCount(24, $bars);
        self::assertSame(1.0, $bars[0]['height']);
        self::assertSame(2.0, $bars[22]['height']);
        self::assertSame(22.0, $bars[23]['height']);
        self::assertSame(92, $bars[23]['x']);
    }
}
