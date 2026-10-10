<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Logging\Application\Command\RevokeSource\RevokeSource;
use Watchdog\Logging\Application\Command\RevokeSource\RevokeSourceHandler;
use Watchdog\Shared\Domain\Permission;

final class RevokeSourceController extends AbstractController
{
    public function __construct(
        private readonly RevokeSourceHandler $revokeSource,
    ) {
    }

    #[Route(
        '/api/sources/{id}/revoke',
        name: 'source_revoke',
        requirements: ['id' => Requirement::UUID],
        methods: ['POST'],
    )]
    #[IsGranted(Permission::SourceManage->value)]
    public function __invoke(string $id): Response
    {
        ($this->revokeSource)(new RevokeSource($id));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
