<?php

declare(strict_types=1);

namespace Watchdog\Project\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Project\Application\Command\DeleteProject\DeleteProject;
use Watchdog\Project\Application\Command\DeleteProject\DeleteProjectHandler;
use Watchdog\Shared\Domain\Permission;

final class DeleteProjectController extends AbstractController
{
    public function __construct(
        private readonly DeleteProjectHandler $deleteProject,
    ) {
    }

    #[Route(
        '/api/projects/{id}',
        name: 'project_delete',
        requirements: ['id' => Requirement::UUID],
        methods: ['DELETE'],
    )]
    #[IsGranted(Permission::ProjectManage->value)]
    public function __invoke(string $id): Response
    {
        ($this->deleteProject)(new DeleteProject($id));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
