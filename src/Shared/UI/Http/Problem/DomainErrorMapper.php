<?php

declare(strict_types=1);

namespace App\Shared\UI\Http\Problem;

use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Error\Forbidden;
use App\Shared\Domain\Error\NotFound;
use Symfony\Component\HttpFoundation\Response;

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
