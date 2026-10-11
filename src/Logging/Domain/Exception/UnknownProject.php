<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Exception;

use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Shared\Domain\Error\DomainError;
use Watchdog\Shared\Domain\Error\NotFound;

/**
 * Same message key as the Project context's own "not found", so clients show one message.
 */
final class UnknownProject extends DomainError implements NotFound
{
    public static function withId(ProjectId $id): self
    {
        return new self(
            message: \sprintf('Project "%s" not found.', $id->value),
            messageKey: 'project.not_found',
            messageParameters: ['%id%' => $id->value],
        );
    }
}
