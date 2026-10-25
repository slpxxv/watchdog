<?php

declare(strict_types=1);

namespace Watchdog\Identity\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Attribute\Route;
use Watchdog\Identity\Application\Port\CurrentActor;
use Watchdog\Identity\Application\Query\GetUser;

final class DebugController extends AbstractController
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly GetUser $getUser,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    #[Route('/debug', name: 'debug', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('identity/debug.html.twig', [
            'user' => ($this->getUser)($this->actor->id()),
            'symfony' => Kernel::VERSION,
            'php' => \PHP_VERSION,
            'environment' => $this->environment,
        ]);
    }
}
