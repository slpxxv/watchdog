<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Logging\Application\Query\ListSources\ListSources;
use Watchdog\Logging\Application\Query\ListSources\ListSourcesHandler;
use Watchdog\Shared\Domain\Permission;

final class ListSourcesController extends AbstractController
{
    public function __construct(
        private readonly ListSourcesHandler $listSources,
    ) {
    }

    #[Route(
        '/api/projects/{projectId}/sources',
        name: 'source_list',
        requirements: ['projectId' => Requirement::UUID],
        methods: ['GET'],
    )]
    #[IsGranted(Permission::SourceManage->value)]
    public function __invoke(string $projectId): JsonResponse
    {
        return $this->json(($this->listSources)(new ListSources($projectId)));
    }
}
