<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Log;

use Watchdog\Logging\Domain\ProjectId;

interface LogStore
{
    /**
     * Writes all entries or none.
     *
     * @param list<LogEntry> $entries
     */
    public function append(array $entries): void;

    /**
     * Newest first, keyset-paginated by (timestamp, id).
     */
    public function search(LogFilter $filter, ?LogCursor $cursor, int $limit): LogPage;

    /**
     * Lines in arrival order after $afterId, plus every line received in the last 10 seconds
     * (rows committed late can carry a lower id; callers drop the duplicates). Only lines with a
     * timestamp from the last hour, so old backfills do not flood a live view.
     *
     * @return list<LogEntry>
     */
    public function tail(LogFilter $filter, int $afterId, \DateTimeImmutable $now, int $limit): array;

    /**
     * Deletes up to $limit lines of the project; returns how many went.
     */
    public function deleteProjectBatch(ProjectId $projectId, int $limit): int;
}
