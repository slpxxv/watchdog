<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command;

use Watchdog\Logging\Domain\Log\LogStore;
use Watchdog\Logging\Domain\ProjectId;

final readonly class PurgeProjectLogs
{
    // Small batches keep each DELETE short, so it never holds locks long enough to stall ingestion.
    public const int BATCH_SIZE = 10_000;

    public function __construct(
        private LogStore $logs,
    ) {
    }

    /**
     * @return int lines deleted
     */
    public function __invoke(string $projectId): int
    {
        $project = ProjectId::fromString($projectId);
        $total = 0;

        do {
            $deleted = $this->logs->deleteProjectBatch($project, self::BATCH_SIZE);
            $total += $deleted;
        } while (self::BATCH_SIZE === $deleted);

        return $total;
    }
}
