<?php

declare(strict_types=1);

namespace App\Project\UI\Http;

use App\Project\Application\Command\RenameProject\RenameProject;
use App\Project\Application\Command\RenameProject\RenameProjectHandler;
use App\Project\Application\Query\GetProject\GetProject;
use App\Project\Application\Query\GetProject\GetProjectHandler;
use App\Project\UI\Http\Request\RenameProjectRequest;
use App\Shared\Domain\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RenameProjectController extends AbstractController
{
    public function __construct(
        private readonly RenameProjectHandler $renameProject,
        private readonly GetProjectHandler $getProject,
    ) {
    }

    #[Route(
        '/api/projects/{id}',
        name: 'project_rename',
        requirements: ['id' => Requirement::UUID],
        methods: ['PATCH'],
    )]
    #[IsGranted(Permission::ProjectManage->value)]
    public function __invoke(string $id, #[MapRequestPayload] RenameProjectRequest $request): JsonResponse
    {
        ($this->renameProject)(new RenameProject(
            id: $id,
            name: $request->name,
        ));

        return $this->json(($this->getProject)(new GetProject($id)));
    }
}
