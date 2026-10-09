<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Exception;

use App\Identity\Domain\User\UserId;
use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Error\NotFound;

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
