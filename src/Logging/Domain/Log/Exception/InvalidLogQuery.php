<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Log\Exception;

use Watchdog\Shared\Domain\Error\DomainError;

/**
 * A search or tail request that cannot be answered as asked. Unlike ingestion, queries are not
 * "fixed": a silently changed filter would show the wrong logs.
 */
final class InvalidLogQuery extends DomainError
{
    public static function because(string $reason): self
    {
        return new self(
            message: $reason,
            messageKey: 'log.query.invalid',
            messageParameters: ['%reason%' => $reason],
        );
    }
}
