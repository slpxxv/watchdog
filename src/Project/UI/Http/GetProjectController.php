<?php

declare(strict_types=1);

namespace Watchdog\Project\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Project\Application\Query\GetProject\GetProject;
use Watchdog\Project\Application\Query\GetProject\GetProjectHandler;
use Watchdog\Shared\Domain\Permission;

final class GetProjectController extends AbstractController
{
    public function __construct(
        private readonly GetProjectHandler $getProject,
    ) {
    }

    #[Route(
        '/api/projects/{id}',
        name: 'project_get',
        requirements: ['id' => Requirement::UUID],
        methods: ['GET'],
    )]
    #[IsGranted(Permission::ProjectView->value)]
    public function __invoke(string $id): JsonResponse
    {
        return $this->json(($this->getProject)(new GetProject($id)));
    }
}
