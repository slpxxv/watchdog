<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command;

use Psr\Clock\ClockInterface;
use Watchdog\Logging\Domain\Source\Exception\SourceNotFound;
use Watchdog\Logging\Domain\Source\SourceId;
use Watchdog\Logging\Domain\Source\SourceRepository;

final readonly class RevokeSource
{
    public function __construct(
        private SourceRepository $sources,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(string $sourceId): void
    {
        $id = SourceId::fromString($sourceId);
        $source = $this->sources->ofId($id) ?? throw SourceNotFound::withId($id);

        $source->revoke($this->clock->now());

        $this->sources->save($source);
    }
}
