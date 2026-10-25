<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command;

use Psr\Clock\ClockInterface;
use Watchdog\Logging\Application\Dto\NewSourceDto;
use Watchdog\Logging\Application\Dto\SourceDto;
use Watchdog\Logging\Application\Port\ProjectCatalog;
use Watchdog\Logging\Domain\Exception\UnknownProject;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\Platform;
use Watchdog\Logging\Domain\Source\Source;
use Watchdog\Logging\Domain\Source\SourceRepository;
use Watchdog\Logging\Domain\Source\SourceToken;

final readonly class CreateSource
{
    public function __construct(
        private SourceRepository $sources,
        private ProjectCatalog $projects,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        string $projectId,
        string $name,
        Platform $platform = Platform::Http,
    ): NewSourceDto {
        $project = ProjectId::fromString($projectId);
        if (!$this->projects->exists($project)) {
            throw UnknownProject::withId($project);
        }

        $token = SourceToken::generate();
        $source = Source::create(
            id: $this->sources->nextIdentity(),
            projectId: $project,
            name: $name,
            token: $token,
            now: $this->clock->now(),
            platform: $platform,
        );

        $this->sources->save($source);

        return new NewSourceDto(
            source: SourceDto::fromSource($source),
            token: $token->value,
        );
    }
}
