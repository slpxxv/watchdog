<?php

declare(strict_types=1);

namespace Watchdog\Project\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Project\Application\Query\ListProjects\ListProjects;
use Watchdog\Project\Application\Query\ListProjects\ListProjectsHandler;
use Watchdog\Shared\Domain\Permission;

final class ListProjectsController extends AbstractController
{
    public function __construct(
        private readonly ListProjectsHandler $listProjects,
    ) {
    }

    #[Route('/api/projects', name: 'project_list', methods: ['GET'])]
    #[IsGranted(Permission::ProjectView->value)]
    public function __invoke(): JsonResponse
    {
        return $this->json(($this->listProjects)(new ListProjects()));
    }
}
