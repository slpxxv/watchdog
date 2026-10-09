<?php

declare(strict_types=1);

namespace App\Project\UI\Http;

use App\Project\Application\Command\CreateProject\CreateProject;
use App\Project\Application\Command\CreateProject\CreateProjectHandler;
use App\Project\Application\Query\GetProject\GetProject;
use App\Project\Application\Query\GetProject\GetProjectHandler;
use App\Project\UI\Http\Request\CreateProjectRequest;
use App\Shared\Domain\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CreateProjectController extends AbstractController
{
    public function __construct(
        private readonly CreateProjectHandler $createProject,
        private readonly GetProjectHandler $getProject,
    ) {
    }

    #[Route('/api/projects', name: 'project_create', methods: ['POST'])]
    #[IsGranted(Permission::ProjectManage->value)]
    public function __invoke(#[MapRequestPayload] CreateProjectRequest $request): JsonResponse
    {
        $id = ($this->createProject)(new CreateProject($request->name))->value;

        return $this->json(
            ($this->getProject)(new GetProject($id)),
            Response::HTTP_CREATED,
            ['Location' => $this->generateUrl('project_get', ['id' => $id])],
        );
    }
}
