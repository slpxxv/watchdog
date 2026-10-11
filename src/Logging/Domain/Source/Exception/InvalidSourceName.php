<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Source\Exception;

use Watchdog\Logging\Domain\Source\Source;
use Watchdog\Shared\Domain\Error\DomainError;

final class InvalidSourceName extends DomainError
{
    public static function for(string $name): self
    {
        return new self(
            message: \sprintf('Source name "%s" must be 1-%d characters.', $name, Source::MAX_NAME_LENGTH),
            messageKey: 'source.name.invalid',
            messageParameters: ['%max%' => Source::MAX_NAME_LENGTH],
        );
    }
}
