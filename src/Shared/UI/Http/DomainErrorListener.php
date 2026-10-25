<?php

declare(strict_types=1);

namespace Watchdog\Shared\UI\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Watchdog\Shared\Domain\Error\DomainError;
use Watchdog\Shared\UI\Http\Problem\DomainErrorMapper;

#[AsEventListener]
final readonly class DomainErrorListener
{
    public function __construct(private DomainErrorMapper $mapper)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $error = $event->getThrowable();
        if (!$error instanceof DomainError) {
            return;
        }

        // Pages: "not found" and "forbidden" become the usual error pages; any other domain error is
        // reported by the action itself (FlashesDomainErrors).
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            $status = $this->mapper->statusOf($error);
            if (Response::HTTP_NOT_FOUND === $status) {
                $event->setThrowable(new NotFoundHttpException($error->getMessage(), $error));
            } elseif (Response::HTTP_FORBIDDEN === $status) {
                $event->setThrowable(new AccessDeniedHttpException($error->getMessage(), $error));
            }

            return;
        }

        $event->setResponse($this->mapper->map($error)->toResponse());
    }
}
