<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Query;

use Watchdog\Logging\Application\Dto\LogEntryDto;
use Watchdog\Logging\Domain\Log\LogEntry;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\SourceRepository;

/**
 * Turns stored lines into DTOs with their source's name, so viewers who cannot manage sources
 * still see where a line came from.
 */
final readonly class LogEntryPresenter
{
    public function __construct(
        private SourceRepository $sources,
    ) {
    }

    /**
     * @param list<LogEntry> $entries
     *
     * @return list<LogEntryDto>
     */
    public function present(ProjectId $projectId, array $entries): array
    {
        if ([] === $entries) {
            return [];
        }

        $names = [];
        foreach ($this->sources->ofProject($projectId) as $source) {
            $names[$source->id()->value] = $source->name();
        }

        return array_map(static fn (LogEntry $e): LogEntryDto => LogEntryDto::fromEntry($e, $names[$e->sourceId->value] ?? null), $entries);
    }
}
