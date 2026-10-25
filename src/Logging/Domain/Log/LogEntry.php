<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Log;

use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\SourceId;

/**
 * One log line. Immutable and not an ORM entity: logs are written and read in bulk through LogStore.
 */
final readonly class LogEntry
{
    /**
     * @param array<string, mixed> $context
     * @param ?int                 $id      assigned by the store; null until written
     */
    public function __construct(
        public ProjectId $projectId,
        public SourceId $sourceId,
        public \DateTimeImmutable $timestamp,
        public \DateTimeImmutable $receivedAt,
        public LogLevel $level,
        public string $message,
        public array $context = [],
        public ?int $id = null,
    ) {
    }
}
