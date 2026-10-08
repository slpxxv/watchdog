<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Error\Forbidden;
use App\Shared\Domain\Error\NotFound;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

#[AsEventListener]
class DomainErrorListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $error = $event->getThrowable();
        if (!$error instanceof DomainError) {
            return;
        }

        $status = match (true) {
            $error instanceof NotFound => Response::HTTP_NOT_FOUND,
            $error instanceof Conflict => Response::HTTP_CONFLICT,
            $error instanceof Forbidden => Response::HTTP_FORBIDDEN,
            default => Response::HTTP_UNPROCESSABLE_ENTITY,
        };

        $event->setResponse(new JsonResponse([
            'type' => 'about:blank',
            'title' => Response::$statusTexts[$status],
            'status' => $status,
            'detail' => $error->getMessage(),
            'messageKey' => $error->messageKey,
            'messageParameters' => $error->messageParameters,
        ], $status, ['Content-Type' => 'application/problem+json']));
    }
}
