<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Dto;

final readonly class SourceActivityDto
{
    /**
     * @param list<int> $hourly lines per hour, oldest first; the last entry is the current hour
     */
    public function __construct(
        public int $lines,
        public ?string $lastLogAt,
        public array $hourly,
    ) {
    }
}
