<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Query;

use Psr\Clock\ClockInterface;
use Watchdog\Logging\Application\Dto\SourceActivityDto;
use Watchdog\Logging\Application\Dto\SourceDto;
use Watchdog\Logging\Application\Port\ProjectCatalog;
use Watchdog\Logging\Domain\Exception\UnknownProject;
use Watchdog\Logging\Domain\Log\LogStore;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\Source;
use Watchdog\Logging\Domain\Source\SourceRepository;

final readonly class ListSources
{
    public const int HOURS = 24;

    public function __construct(
        private SourceRepository $sources,
        private ProjectCatalog $projects,
        private LogStore $logs,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Each source with its activity over the last 24 hours, the current hour included.
     *
     * @return list<SourceDto>
     */
    public function __invoke(string $projectId): array
    {
        $project = ProjectId::fromString($projectId);
        if (!$this->projects->exists($project)) {
            throw UnknownProject::withId($project);
        }

        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $firstHour = $now->setTime((int) $now->format('G'), 0)->modify(\sprintf('-%d hours', self::HOURS - 1));

        $hourly = [];
        $lastAt = [];
        foreach ($this->logs->volumeByHour($project, $firstHour) as $volume) {
            $id = $volume->sourceId->value;
            $slot = intdiv($volume->hour->getTimestamp() - $firstHour->getTimestamp(), 3600);
            if ($slot < 0 || $slot >= self::HOURS) {
                continue;
            }
            $hourly[$id] ??= array_fill(0, self::HOURS, 0);
            $hourly[$id][$slot] += $volume->lines;
            $lastAt[$id] = max($lastAt[$id] ?? $volume->lastAt, $volume->lastAt);
        }

        return array_map(static function (Source $source) use ($hourly, $lastAt): SourceDto {
            $id = $source->id()->value;
            $counts = $hourly[$id] ?? array_fill(0, self::HOURS, 0);

            return SourceDto::fromSource($source, new SourceActivityDto(
                lines: array_sum($counts),
                lastLogAt: isset($lastAt[$id]) ? $lastAt[$id]->format(\DATE_ATOM) : null,
                hourly: $counts,
            ));
        }, $this->sources->ofProject($project));
    }
}
