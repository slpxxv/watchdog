<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Source\Exception;

use Watchdog\Logging\Domain\Source\SourceId;
use Watchdog\Shared\Domain\Error\DomainError;
use Watchdog\Shared\Domain\Error\NotFound;

final class SourceNotFound extends DomainError implements NotFound
{
    public static function withId(SourceId $id): self
    {
        return new self(
            message: \sprintf('Source "%s" not found.', $id->value),
            messageKey: 'source.not_found',
            messageParameters: ['%id%' => $id->value],
        );
    }
}
