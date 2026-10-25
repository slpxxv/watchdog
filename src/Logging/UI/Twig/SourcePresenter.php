<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Twig;

use Psr\Clock\ClockInterface;
use Twig\Attribute\AsTwigFilter;
use Watchdog\Logging\Application\Dto\SourceDto;

final readonly class SourcePresenter
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    #[AsTwigFilter('source_status')]
    public function status(SourceDto $source): string
    {
        if (null !== $source->revokedAt) {
            return 'Odwołane';
        }
        $last = $source->activity?->lastLogAt;
        if (null === $last) {
            return 'Brak danych z ostatnich 24 h';
        }

        $seconds = max(0, $this->clock->now()->getTimestamp() - (new \DateTimeImmutable($last))->getTimestamp());

        return match (true) {
            $seconds < 60 => 'Ostatni log przed chwilą',
            $seconds < 3600 => \sprintf('Ostatni log %d min temu', intdiv($seconds, 60)),
            default => \sprintf('Ostatni log %d godz. temu', intdiv($seconds, 3600)),
        };
    }

    /**
     * The 24 hourly bars as x/height pairs for a 96x24 SVG; an empty hour keeps a 1px baseline.
     *
     * @return list<array{x: int, height: float}>
     */
    #[AsTwigFilter('activity_bars')]
    public function bars(SourceDto $source): array
    {
        $hourly = $source->activity->hourly ?? [];
        $peak = max(1, ...$hourly ?: [1]);

        return array_map(
            static fn (int $lines, int $hour): array => ['x' => $hour * 4, 'height' => $lines > 0 ? max(2.0, $lines / $peak * 22.0) : 1.0],
            $hourly,
            array_keys($hourly),
        );
    }
}
