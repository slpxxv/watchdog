<?php

declare(strict_types=1);

namespace Watchdog\Tests\Double;

use Watchdog\Logging\Domain\Log\LogCursor;
use Watchdog\Logging\Domain\Log\LogEntry;
use Watchdog\Logging\Domain\Log\LogFilter;
use Watchdog\Logging\Domain\Log\LogPage;
use Watchdog\Logging\Domain\Log\LogStore;
use Watchdog\Logging\Domain\Log\SourceVolume;
use Watchdog\Logging\Domain\ProjectId;

/**
 * Answers with prepared entries and remembers what it was asked.
 */
final class SpyLogStore implements LogStore
{
    /** @var list<LogEntry> */
    public array $entries = [];
    public ?LogCursor $next = null;
    public ?LogFilter $lastFilter = null;
    public ?LogCursor $lastCursor = null;
    public ?int $lastLimit = null;
    public ?int $lastAfterId = null;
    /** @var list<SourceVolume> */
    public array $volumes = [];
    public ?\DateTimeImmutable $lastSince = null;

    public function append(array $entries): void
    {
        array_push($this->entries, ...$entries);
    }

    public function search(LogFilter $filter, ?LogCursor $cursor, int $limit): LogPage
    {
        [$this->lastFilter, $this->lastCursor, $this->lastLimit] = [$filter, $cursor, $limit];

        return new LogPage($this->entries, $this->next);
    }

    public function tail(LogFilter $filter, int $afterId, \DateTimeImmutable $now, int $limit): array
    {
        [$this->lastFilter, $this->lastAfterId, $this->lastLimit] = [$filter, $afterId, $limit];

        return $this->entries;
    }

    public function volumeByHour(ProjectId $projectId, \DateTimeImmutable $since): array
    {
        $this->lastSince = $since;

        return $this->volumes;
    }

    public function deleteProjectBatch(ProjectId $projectId, int $limit): int
    {
        return 0;
    }
}
