<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Logging\Application\Command\RotateSourceToken\RotateSourceToken;
use Watchdog\Logging\Application\Command\RotateSourceToken\RotateSourceTokenHandler;
use Watchdog\Shared\Domain\Permission;

final class RotateSourceTokenController extends AbstractController
{
    public function __construct(
        private readonly RotateSourceTokenHandler $rotateSourceToken,
    ) {
    }

    #[Route(
        '/api/sources/{id}/rotate-token',
        name: 'source_rotate_token',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsGranted(Permission::SourceManage->value)]
    public function __invoke(string $id): JsonResponse
    {
        return $this->json(
            ($this->rotateSourceToken)(new RotateSourceToken($id)),
            Response::HTTP_OK,
            ['Cache-Control' => 'no-store'],
        );
    }
}
