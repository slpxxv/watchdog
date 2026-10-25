<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Logging\Application\Command\RotateSourceToken;
use Watchdog\Project\Application\Query\GetProject;
use Watchdog\Shared\Domain\Error\DomainError;
use Watchdog\Shared\Domain\Permission;
use Watchdog\Shared\UI\Http\FlashesDomainErrors;

final class RotateSourceTokenController extends AbstractController
{
    use FlashesDomainErrors;

    public function __construct(
        private readonly RotateSourceToken $rotateSourceToken,
        private readonly GetProject $getProject,
    ) {
    }

    #[Route(
        '/projects/{projectId}/sources/{sourceId}/rotate',
        name: 'source_rotate',
        requirements: ['projectId' => Requirement::UUID, 'sourceId' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsGranted(Permission::SourceManage->value)]
    #[IsCsrfTokenValid('source_rotate', tokenKey: '_token')]
    public function __invoke(string $projectId, string $sourceId): Response
    {
        try {
            $created = ($this->rotateSourceToken)($sourceId);
        } catch (DomainError $error) {
            $this->flashDomainError($error);

            return $this->redirectToRoute('source_list', ['projectId' => $projectId]);
        }

        $response = $this->render('logging/source_ready.html.twig', [
            'project' => ($this->getProject)($projectId),
            'created' => $created,
            'platform' => $created->source->platform,
            'heading' => 'Nowy token źródła „'.$created->source->name.'”',
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
