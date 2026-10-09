<?php

declare(strict_types=1);

namespace Watchdog\Identity\Domain\User\Exception;

use Watchdog\Identity\Domain\User\UserId;
use Watchdog\Shared\Domain\Error\DomainError;
use Watchdog\Shared\Domain\Error\NotFound;

final class UserNotFound extends DomainError implements NotFound
{
    public static function withId(UserId $id): self
    {
        return new self(
            message: \sprintf('User "%s" not found.', $id->value),
            messageKey: 'identity.user.not_found',
            messageParameters: ['%id%' => $id->value],
        );
    }
}
