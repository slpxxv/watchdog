<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command\RotateSourceToken;

final readonly class RotateSourceToken
{
    public function __construct(
        public string $sourceId,
    ) {
    }
}
