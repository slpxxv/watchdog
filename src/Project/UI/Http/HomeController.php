<?php

declare(strict_types=1);

namespace Watchdog\Project\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Watchdog\Project\UI\Twig\Shell;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly Shell $shell,
    ) {
    }

    /**
     * Back to the project last opened, or to the project list.
     */
    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): RedirectResponse
    {
        $projectId = ($this->shell)()['projectId'];

        return null === $projectId
            ? $this->redirectToRoute('project_list')
            : $this->redirectToRoute('log_live', ['projectId' => $projectId]);
    }

    #[Route('/projects/{projectId}', name: 'project_show', requirements: ['projectId' => Requirement::UUID], methods: ['GET'])]
    public function show(string $projectId): RedirectResponse
    {
        return $this->redirectToRoute('log_live', ['projectId' => $projectId]);
    }

    /**
     * The sidebar's project selector: same section of another project.
     */
    #[Route('/switch-project', name: 'project_switch', methods: ['GET'])]
    public function switch(Request $request): RedirectResponse
    {
        $route = ['events' => 'log_events', 'sources' => 'source_list'][$request->query->getString('section')] ?? 'log_live';
        $projectId = $request->query->getString('project');
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $projectId)) {
            return $this->redirectToRoute('project_list');
        }

        return $this->redirectToRoute($route, ['projectId' => $projectId]);
    }
}
