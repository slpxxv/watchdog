<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Log;

use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\SourceId;

/**
 * What to read from one project's logs. Time bounds are optional here; callers that search
 * should always pass them, or the query reads every partition.
 */
final readonly class LogFilter
{
    /**
     * @param list<SourceId>        $sourceIds empty means every source
     * @param ?array<string, mixed> $context   lines whose context contains this object
     */
    public function __construct(
        public ProjectId $projectId,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public ?LogLevel $minLevel = null,
        public array $sourceIds = [],
        public ?string $text = null,
        public ?array $context = null,
    ) {
        if (null !== $from && null !== $to && $from >= $to) {
            throw new \InvalidArgumentException('The "from" bound must be before "to".');
        }
    }
}
