<?php

declare(strict_types=1);

namespace Watchdog\Project\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Project\Application\Command\DeleteProject;
use Watchdog\Shared\Domain\Permission;

final class DeleteProjectController extends AbstractController
{
    public function __construct(
        private readonly DeleteProject $deleteProject,
    ) {
    }

    #[Route(
        '/projects/{projectId}/delete',
        name: 'project_delete',
        requirements: ['projectId' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsGranted(Permission::ProjectManage->value)]
    #[IsCsrfTokenValid('project_delete', tokenKey: '_token')]
    public function __invoke(string $projectId, Request $request): Response
    {
        ($this->deleteProject)($projectId);
        $this->addFlash('success', 'Projekt został usunięty.');

        return $this->redirectToRoute('project_list');
    }
}
