<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command\PurgeProjectLogs;

use Watchdog\Logging\Domain\Log\LogStore;
use Watchdog\Logging\Domain\ProjectId;

final readonly class PurgeProjectLogsHandler
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
    public function __invoke(PurgeProjectLogs $command): int
    {
        $projectId = ProjectId::fromString($command->projectId);
        $total = 0;

        do {
            $deleted = $this->logs->deleteProjectBatch($projectId, self::BATCH_SIZE);
            $total += $deleted;
        } while (self::BATCH_SIZE === $deleted);

        return $total;
    }
}
