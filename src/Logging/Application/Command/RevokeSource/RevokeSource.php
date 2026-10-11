<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command\RevokeSource;

final readonly class RevokeSource
{
    public function __construct(
        public string $sourceId,
    ) {
    }
}
