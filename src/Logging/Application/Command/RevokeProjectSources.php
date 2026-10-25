<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command;

use Psr\Clock\ClockInterface;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\SourceRepository;

/**
 * Runs when a project is deleted: its tokens must stop working at once.
 */
final readonly class RevokeProjectSources
{
    public function __construct(
        private SourceRepository $sources,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(string $projectId): void
    {
        $now = $this->clock->now();

        foreach ($this->sources->ofProject(ProjectId::fromString($projectId)) as $source) {
            $source->revoke($now);
            $this->sources->save($source);
        }
    }
}
