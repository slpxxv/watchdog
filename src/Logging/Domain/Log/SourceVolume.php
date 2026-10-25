<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Log;

use Watchdog\Logging\Domain\Source\SourceId;

/**
 * Lines one source sent within one hour (by line timestamp), and the newest of them.
 */
final readonly class SourceVolume
{
    public function __construct(
        public SourceId $sourceId,
        public \DateTimeImmutable $hour,
        public int $lines,
        public \DateTimeImmutable $lastAt,
    ) {
    }
}
