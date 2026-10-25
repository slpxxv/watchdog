<?php

declare(strict_types=1);

namespace Watchdog\Project\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Project\Application\Command\RenameProject;
use Watchdog\Project\UI\Http\Form\ProjectInput;
use Watchdog\Project\UI\Http\Form\ProjectType;
use Watchdog\Shared\Domain\Error\DomainError;
use Watchdog\Shared\Domain\Permission;
use Watchdog\Shared\UI\Http\FlashesDomainErrors;

final class RenameProjectController extends AbstractController
{
    use FlashesDomainErrors;

    public function __construct(
        private readonly RenameProject $renameProject,
    ) {
    }

    #[Route(
        '/projects/{projectId}/rename',
        name: 'project_rename',
        requirements: ['projectId' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsGranted(Permission::ProjectManage->value)]
    public function __invoke(string $projectId, Request $request): Response
    {
        $form = $this->createForm(ProjectType::class, new ProjectInput());
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Nazwa projektu musi mieć od 1 do 100 znaków.');

            return $this->redirectToRoute('project_list');
        }

        try {
            ($this->renameProject)(id: $projectId, name: $form->getData()->name);
            $this->addFlash('success', 'Nazwa projektu została zmieniona.');
        } catch (DomainError $error) {
            $this->flashDomainError($error);
        }

        return $this->redirectToRoute('project_list');
    }
}
