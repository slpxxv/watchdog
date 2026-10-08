<?php

declare(strict_types=1);

namespace App\Identity\UI\Http;

use App\Identity\Application\Port\CurrentActor;
use App\Identity\Application\Query\GetUser\GetUser;
use App\Identity\Application\Query\GetUser\GetUserHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SecurityController extends AbstractController
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly GetUserHandler $getUser,
    ) {
    }

    /**
     * Reached only after json_login has authenticated the request; failures never get here.
     */
    #[Route('/api/login', name: 'identity_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        return $this->me();
    }

    #[Route('/api/me', name: 'identity_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return $this->json(($this->getUser)(new GetUser($this->actor->id())));
    }
}
