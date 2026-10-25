<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command;

use Watchdog\Logging\Application\Dto\NewSourceDto;
use Watchdog\Logging\Application\Dto\SourceDto;
use Watchdog\Logging\Domain\Source\Exception\SourceNotFound;
use Watchdog\Logging\Domain\Source\SourceId;
use Watchdog\Logging\Domain\Source\SourceRepository;
use Watchdog\Logging\Domain\Source\SourceToken;

final readonly class RotateSourceToken
{
    public function __construct(
        private SourceRepository $sources,
    ) {
    }

    public function __invoke(string $sourceId): NewSourceDto
    {
        $id = SourceId::fromString($sourceId);
        $source = $this->sources->ofId($id) ?? throw SourceNotFound::withId($id);

        $token = SourceToken::generate();
        $source->rotateToken($token);

        $this->sources->save($source);

        return new NewSourceDto(
            source: SourceDto::fromSource($source),
            token: $token->value,
        );
    }
}
