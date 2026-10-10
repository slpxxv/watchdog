<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command\CreateSource;

use Psr\Clock\ClockInterface;
use Watchdog\Logging\Application\Dto\NewSourceDto;
use Watchdog\Logging\Application\Dto\SourceDto;
use Watchdog\Logging\Application\Port\ProjectCatalog;
use Watchdog\Logging\Domain\Exception\UnknownProject;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\Source;
use Watchdog\Logging\Domain\Source\SourceRepository;
use Watchdog\Logging\Domain\Source\SourceToken;

final readonly class CreateSourceHandler
{
    public function __construct(
        private SourceRepository $sources,
        private ProjectCatalog $projects,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateSource $command): NewSourceDto
    {
        $projectId = ProjectId::fromString($command->projectId);
        if (!$this->projects->exists($projectId)) {
            throw UnknownProject::withId($projectId);
        }

        $token = SourceToken::generate();
        $source = Source::create(
            id: $this->sources->nextIdentity(),
            projectId: $projectId,
            name: $command->name,
            token: $token,
            now: $this->clock->now(),
        );

        $this->sources->save($source);

        return new NewSourceDto(
            source: SourceDto::fromSource($source),
            token: $token->value,
        );
    }
}
