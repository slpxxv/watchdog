<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Logging\Application\Query\TailLogs;
use Watchdog\Logging\UI\Http\Request\TailLogsRequest;
use Watchdog\Shared\Domain\Permission;

/**
 * Polled by the live view every few seconds.
 */
final class TailLogsController extends AbstractController
{
    public function __construct(
        private readonly TailLogs $tailLogs,
    ) {
    }

    #[Route(
        '/api/projects/{projectId}/logs/tail',
        name: 'log_tail',
        requirements: ['projectId' => Requirement::UUID],
        methods: ['GET'],
    )]
    #[IsGranted(Permission::LogView->value)]
    public function __invoke(
        string $projectId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY, mapWhenEmpty: true)] TailLogsRequest $request,
    ): JsonResponse {
        return $this->json(($this->tailLogs)(
            projectId: $projectId,
            afterId: $request->after,
            filters: $request->filters(),
            limit: $request->limit,
        ));
    }
}
