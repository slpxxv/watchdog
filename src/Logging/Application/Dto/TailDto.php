<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Dto;

final readonly class TailDto
{
    /**
     * @param list<LogEntryDto> $items  arrival order; may repeat lines from the previous call (drop by id)
     * @param int               $lastId pass back as "after" on the next call
     */
    public function __construct(
        public array $items,
        public int $lastId,
    ) {
    }
}
