<?php

declare(strict_types=1);

namespace Watchdog\Project\Domain\Exception;

use Watchdog\Project\Domain\ProjectId;
use Watchdog\Shared\Domain\Error\DomainError;
use Watchdog\Shared\Domain\Error\NotFound;

final class ProjectNotFound extends DomainError implements NotFound
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
