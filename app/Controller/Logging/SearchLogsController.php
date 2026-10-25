<?php

declare(strict_types=1);

namespace Watchdog\App\Controller\Logging;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\App\Request\Logging\SearchLogsRequest;
use Watchdog\Logging\Application\Query\SearchLogs\SearchLogsHandler;
use Watchdog\Shared\Domain\Permission;

final class SearchLogsController extends AbstractController
{
    public function __construct(
        private readonly SearchLogsHandler $searchLogs,
    ) {
    }

    #[Route(
        '/api/projects/{projectId}/logs',
        name: 'log_search',
        requirements: ['projectId' => Requirement::UUID],
        methods: ['GET'],
    )]
    #[IsGranted(Permission::LogView->value)]
    public function __invoke(
        string $projectId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY, mapWhenEmpty: true)] SearchLogsRequest $request,
    ): JsonResponse {
        return $this->json(($this->searchLogs)($request->toQuery($projectId)));
    }
}
