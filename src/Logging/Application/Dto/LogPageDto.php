<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Dto;

final readonly class LogPageDto
{
    /**
     * @param list<LogEntryDto> $items      newest first
     * @param ?string           $nextCursor pass back as "cursor" for older lines; null at the end
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
        public string $from,
        public ?string $to,
    ) {
    }
}
