<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Dto;

use Watchdog\Logging\Domain\Log\LogEntry;

final readonly class LogEntryDto
{
    private const string TIMESTAMP = 'Y-m-d\TH:i:s.vP'; // milliseconds: enough to order lines by eye

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public int $id,
        public string $timestamp,
        public string $receivedAt,
        public string $level,
        public string $message,
        public array $context,
        public string $sourceId,
        public ?string $sourceName,
    ) {
    }

    public static function fromEntry(LogEntry $entry, ?string $sourceName): self
    {
        return new self(
            id: $entry->id ?? throw new \LogicException('Only stored entries can be shown.'),
            timestamp: $entry->timestamp->format(self::TIMESTAMP),
            receivedAt: $entry->receivedAt->format(self::TIMESTAMP),
            level: $entry->level->label(),
            message: $entry->message,
            context: $entry->context,
            sourceId: $entry->sourceId->value,
            sourceName: $sourceName,
        );
    }
}
