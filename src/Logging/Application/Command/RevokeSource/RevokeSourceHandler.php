<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command\RevokeSource;

use Psr\Clock\ClockInterface;
use Watchdog\Logging\Domain\Source\Exception\SourceNotFound;
use Watchdog\Logging\Domain\Source\SourceId;
use Watchdog\Logging\Domain\Source\SourceRepository;

final readonly class RevokeSourceHandler
{
    public function __construct(
        private SourceRepository $sources,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokeSource $command): void
    {
        $id = SourceId::fromString($command->sourceId);
        $source = $this->sources->ofId($id) ?? throw SourceNotFound::withId($id);

        $source->revoke($this->clock->now());

        $this->sources->save($source);
    }
}
