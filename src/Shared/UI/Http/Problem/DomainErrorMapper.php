<?php

declare(strict_types=1);

namespace Watchdog\Shared\UI\Http\Problem;

use Symfony\Component\HttpFoundation\Response;
use Watchdog\Shared\Domain\Error\Conflict;
use Watchdog\Shared\Domain\Error\DomainError;
use Watchdog\Shared\Domain\Error\Forbidden;
use Watchdog\Shared\Domain\Error\NotFound;

final readonly class DomainErrorMapper
{
    public const string MESSAGE_KEY = 'messageKey';
    public const string MESSAGE_PARAMETERS = 'messageParameters';

    private const array STATUS_BY_MARKER = [
        NotFound::class => Response::HTTP_NOT_FOUND,
        Conflict::class => Response::HTTP_CONFLICT,
        Forbidden::class => Response::HTTP_FORBIDDEN,
    ];
    private const int DEFAULT_STATUS = Response::HTTP_UNPROCESSABLE_ENTITY;

    public function map(DomainError $error): ProblemDetails
    {
        return ProblemDetails::forStatus(
            status: $this->statusOf($error),
            detail: $error->getMessage(),
            extensions: [
                self::MESSAGE_KEY => $error->messageKey,
                self::MESSAGE_PARAMETERS => $error->messageParameters,
            ],
        );
    }

    public function statusOf(DomainError $error): int
    {
        foreach (self::STATUS_BY_MARKER as $marker => $status) {
            if ($error instanceof $marker) {
                return $status;
            }
        }

        return self::DEFAULT_STATUS;
    }
}
