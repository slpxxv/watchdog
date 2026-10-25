<?php

declare(strict_types=1);

namespace Watchdog\App\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Watchdog\App\Http\Problem\DomainErrorMapper;
use Watchdog\Shared\Domain\Error\DomainError;

#[AsEventListener]
final readonly class DomainErrorListener
{
    public function __construct(private DomainErrorMapper $mapper)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $error = $event->getThrowable();
        if ($error instanceof DomainError) {
            $event->setResponse(
                $this->mapper->map(
                    $error
                )->toResponse()
            );
        }
    }
}
