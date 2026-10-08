<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use App\Shared\Domain\Error\DomainError;
use App\Shared\UI\Http\Problem\DomainErrorMapper;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

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
