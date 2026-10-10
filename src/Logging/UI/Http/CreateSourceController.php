<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Logging\Application\Command\CreateSource\CreateSource;
use Watchdog\Logging\Application\Command\CreateSource\CreateSourceHandler;
use Watchdog\Logging\UI\Http\Request\CreateSourceRequest;
use Watchdog\Shared\Domain\Permission;

final class CreateSourceController extends AbstractController
{
    public function __construct(
        private readonly CreateSourceHandler $createSource,
    ) {
    }

    #[Route(
        '/api/projects/{projectId}/sources',
        name: 'source_create',
        requirements: ['projectId' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsGranted(Permission::SourceManage->value)]
    public function __invoke(string $projectId, #[MapRequestPayload] CreateSourceRequest $request): JsonResponse
    {
        $created = ($this->createSource)(new CreateSource(
            projectId: $projectId,
            name: $request->name,
        ));

        // The token is shown once; keep it out of any shared or browser cache.
        return $this->json($created, Response::HTTP_CREATED, ['Cache-Control' => 'no-store']);
    }
}
