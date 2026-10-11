<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Source\Exception;

use Watchdog\Logging\Domain\Source\SourceId;
use Watchdog\Shared\Domain\Error\Conflict;
use Watchdog\Shared\Domain\Error\DomainError;

final class SourceRevoked extends DomainError implements Conflict
{
    public static function withId(SourceId $id): self
    {
        return new self(
            message: \sprintf('Source "%s" is revoked.', $id->value),
            messageKey: 'source.revoked',
            messageParameters: ['%id%' => $id->value],
        );
    }
}
