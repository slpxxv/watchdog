<?php

declare(strict_types=1);

namespace Watchdog\Project\UI\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Attribute\AsTwigFunction;
use Watchdog\Project\Application\Dto\ProjectDto;
use Watchdog\Project\Application\Query\ListProjects;
use Watchdog\Shared\Domain\Permission;

/**
 * What the sidebar needs on every page: the project list and the project it points at. Outside a
 * project (Projekty, Debug) it keeps pointing at the last one opened.
 */
final readonly class Shell
{
    public const string LAST_PROJECT = 'last_project';

    public function __construct(
        private ListProjects $listProjects,
        private AuthorizationCheckerInterface $authorization,
        private RequestStack $requests,
    ) {
    }

    /**
     * @return array{projects: list<ProjectDto>, projectId: ?string}
     */
    #[AsTwigFunction('shell')]
    public function __invoke(): array
    {
        if (!$this->authorization->isGranted(Permission::ProjectView->value)) {
            return ['projects' => [], 'projectId' => null];
        }

        $projects = ($this->listProjects)();
        $request = $this->requests->getCurrentRequest();
        $candidates = [
            $request?->attributes->get('projectId'),
            $request?->hasSession() ? $request->getSession()->get(self::LAST_PROJECT) : null,
        ];

        foreach ($candidates as $id) {
            foreach ($projects as $project) {
                if ($project->id === $id) {
                    return ['projects' => $projects, 'projectId' => $id];
                }
            }
        }

        return ['projects' => $projects, 'projectId' => null];
    }
}
