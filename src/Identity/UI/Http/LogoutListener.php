<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * The SPA calls logout via fetch: answer 204 instead of the default redirect (priority 64).
 */
#[AsEventListener(priority: 65)]
final class LogoutListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(new Response(status: Response::HTTP_NO_CONTENT));
    }
}
