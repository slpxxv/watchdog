<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Logging\Application\Command\CreateSource;
use Watchdog\Logging\UI\Http\Form\SourceInput;
use Watchdog\Logging\UI\Http\Form\SourceType;
use Watchdog\Project\Application\Query\GetProject;
use Watchdog\Shared\Domain\Error\DomainError;
use Watchdog\Shared\Domain\Permission;
use Watchdog\Shared\UI\Http\FlashesDomainErrors;

final class NewSourceController extends AbstractController
{
    use FlashesDomainErrors;

    public function __construct(
        private readonly CreateSource $createSource,
        private readonly GetProject $getProject,
    ) {
    }

    /**
     * The token is shown once, on the page that answers the POST (no redirect, so it is never stored
     * in the session or in the browser history; the form opts out of Turbo for the same reason).
     */
    #[Route(
        '/projects/{projectId}/sources/new',
        name: 'source_new',
        requirements: ['projectId' => Requirement::UUID],
        methods: ['GET', 'POST'],
    )]
    #[IsGranted(Permission::SourceManage->value)]
    public function __invoke(string $projectId, Request $request): Response
    {
        $project = ($this->getProject)($projectId);
        $form = $this->createForm(SourceType::class, new SourceInput());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $input = $form->getData();
                $created = ($this->createSource)(
                    projectId: $projectId,
                    name: $input->name,
                    platform: $input->platform,
                );

                $response = $this->render('logging/source_ready.html.twig', [
                    'project' => $project,
                    'created' => $created,
                    'platform' => $input->platform->value,
                    'heading' => 'Źródło „'.$created->source->name.'” jest gotowe',
                ]);
                $response->headers->set('Cache-Control', 'no-store');

                return $response;
            } catch (DomainError $error) {
                $this->flashDomainError($error);
            }
        }

        return $this->render('logging/source_new.html.twig', [
            'project' => $project,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
