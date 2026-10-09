<?php

declare(strict_types=1);

namespace App\Project\UI\Http;

use App\Project\Application\Query\GetProject\GetProject;
use App\Project\Application\Query\GetProject\GetProjectHandler;
use App\Shared\Domain\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
