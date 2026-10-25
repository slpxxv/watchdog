<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Logging\Application\Command\RevokeSource;
use Watchdog\Shared\Domain\Error\DomainError;
use Watchdog\Shared\Domain\Permission;
use Watchdog\Shared\UI\Http\FlashesDomainErrors;

final class RevokeSourceController extends AbstractController
{
    use FlashesDomainErrors;

    public function __construct(
        private readonly RevokeSource $revokeSource,
    ) {
    }

    #[Route(
        '/projects/{projectId}/sources/{sourceId}/revoke',
        name: 'source_revoke',
        requirements: ['projectId' => Requirement::UUID, 'sourceId' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsGranted(Permission::SourceManage->value)]
    #[IsCsrfTokenValid('source_revoke', tokenKey: '_token')]
    public function __invoke(string $projectId, string $sourceId): Response
    {
        try {
            ($this->revokeSource)($sourceId);
            $this->addFlash('success', 'Źródło zostało odwołane.');
        } catch (DomainError $error) {
            $this->flashDomainError($error);
        }

        return $this->redirectToRoute('source_list', ['projectId' => $projectId]);
    }
}
