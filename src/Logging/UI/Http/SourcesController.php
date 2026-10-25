<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Logging\Application\Dto\SourceDto;
use Watchdog\Logging\Application\Query\ListSources;
use Watchdog\Project\Application\Query\GetProject;
use Watchdog\Shared\Domain\Permission;

final class SourcesController extends AbstractController
{
    public function __construct(
        private readonly ListSources $listSources,
        private readonly GetProject $getProject,
    ) {
    }

    #[Route(
        '/projects/{projectId}/sources',
        name: 'source_list',
        requirements: ['projectId' => Requirement::UUID],
        methods: ['GET'],
    )]
    #[IsGranted(Permission::SourceManage->value)]
    public function __invoke(string $projectId, Request $request): Response
    {
        $project = ($this->getProject)($projectId);
        $sources = ($this->listSources)($projectId);

        $filter = trim($request->query->getString('q'));
        $shown = '' === $filter
            ? $sources
            : array_values(array_filter($sources, static fn (SourceDto $s): bool => str_contains(mb_strtolower($s->name), mb_strtolower($filter))));

        return $this->render('logging/sources.html.twig', [
            'project' => $project,
            'sources' => $sources,
            'shown' => $shown,
            'filter' => $filter,
        ]);
    }
}
