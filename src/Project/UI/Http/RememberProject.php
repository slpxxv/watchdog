<?php

declare(strict_types=1);

namespace Watchdog\Project\UI\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Watchdog\Project\UI\Twig\Shell;

/**
 * Pages under /projects/{projectId} remember the project, so "/" and the sidebar come back to it.
 */
#[AsEventListener]
final class RememberProject
{
    public function __invoke(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $projectId = $request->attributes->get('projectId');
        if ($event->isMainRequest() && $request->isMethod('GET') && \is_string($projectId) && $request->hasSession()) {
            $request->getSession()->set(Shell::LAST_PROJECT, $projectId);
        }
    }
}
