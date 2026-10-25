<?php

declare(strict_types=1);

namespace Watchdog\Project\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Project\Application\Command\CreateProject;
use Watchdog\Project\Application\Query\ListProjects;
use Watchdog\Project\UI\Http\Form\ProjectInput;
use Watchdog\Project\UI\Http\Form\ProjectType;
use Watchdog\Shared\Domain\Error\DomainError;
use Watchdog\Shared\Domain\Permission;
use Watchdog\Shared\UI\Http\FlashesDomainErrors;

final class ProjectsController extends AbstractController
{
    use FlashesDomainErrors;

    public function __construct(
        private readonly ListProjects $listProjects,
        private readonly CreateProject $createProject,
    ) {
    }

    #[Route('/projects', name: 'project_list', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::ProjectView->value)]
    public function __invoke(Request $request): Response
    {
        $form = $this->createForm(ProjectType::class, new ProjectInput());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted(Permission::ProjectManage->value);

            try {
                ($this->createProject)($form->getData()->name);
                $this->addFlash('success', 'Projekt został dodany.');

                return $this->redirectToRoute('project_list');
            } catch (DomainError $error) {
                $this->flashDomainError($error);
            }
        }

        return $this->render('project/index.html.twig', [
            'projects' => ($this->listProjects)(),
            'form' => $form,
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
