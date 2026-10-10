<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Dto;

/**
 * The only place the plain token ever leaves the server: right after it is created or rotated.
 */
final readonly class NewSourceDto
{
    public function __construct(
        public SourceDto $source,
        #[\SensitiveParameter] public string $token,
    ) {
    }
}
