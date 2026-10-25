<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command;

use Psr\Clock\ClockInterface;
use Watchdog\Logging\Application\Dto\IngestResultDto;
use Watchdog\Logging\Application\Service\LineNormalizer;
use Watchdog\Logging\Domain\Log\LogStore;
use Watchdog\Logging\Domain\Source\Exception\SourceNotFound;
use Watchdog\Logging\Domain\Source\Exception\SourceRevoked;
use Watchdog\Logging\Domain\Source\SourceId;
use Watchdog\Logging\Domain\Source\SourceRepository;

final readonly class IngestLogs
{
    private LineNormalizer $normalizer;

    public function __construct(
        private SourceRepository $sources,
        private LogStore $logs,
        private ClockInterface $clock,
    ) {
        $this->normalizer = new LineNormalizer();
    }

    /**
     * @param list<array<array-key, mixed>> $lines decoded JSON objects, one per log line
     */
    public function __invoke(
        string $sourceId,
        array $lines,
    ): IngestResultDto {
        $id = SourceId::fromString($sourceId);
        $source = $this->sources->ofId($id) ?? throw SourceNotFound::withId($id);
        if ($source->isRevoked()) {
            throw SourceRevoked::withId($id);
        }

        $now = $this->clock->now();
        $entries = [];
        $fixed = 0;
        foreach ($lines as $line) {
            $normalized = $this->normalizer->normalize($line, $source->projectId(), $id, $now);
            $entries[] = $normalized->entry;
            $fixed += (int) $normalized->wasFixed;
        }

        $this->logs->append($entries);

        return new IngestResultDto(accepted: \count($entries), normalized: $fixed);
    }
}
