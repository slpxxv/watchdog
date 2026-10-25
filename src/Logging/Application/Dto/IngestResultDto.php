<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Dto;

final readonly class IngestResultDto
{
    /**
     * @param int $normalized lines stored with a fix (level, timestamp, size...); details in their context
     */
    public function __construct(
        public int $accepted,
        public int $normalized,
    ) {
    }
}
