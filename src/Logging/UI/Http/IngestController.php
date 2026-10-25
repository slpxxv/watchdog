<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Logging\Application\Command\IngestLogs\IngestLogs;
use Watchdog\Logging\Application\Command\IngestLogs\IngestLogsHandler;
use Watchdog\Logging\UI\Http\Ingest\IngestPayloadReader;
use Watchdog\Logging\UI\Http\Ingest\InvalidPayload;
use Watchdog\Shared\UI\Http\Problem\ProblemDetails;

/**
 * Not #[MapRequestPayload]: one bad line must not reject the batch, and NDJSON or gzip bodies are
 * not what the serializer reads. IngestPayloadReader decodes, LineNormalizer fixes line by line.
 */
final class IngestController extends AbstractController
{
    public function __construct(
        private readonly IngestLogsHandler $ingestLogs,
        private readonly IngestPayloadReader $reader,
        private readonly RateLimiterFactoryInterface $logIngestLimiter,
    ) {
    }

    #[Route('/api/v1/ingest/logs', name: 'log_ingest', methods: ['POST'])]
    #[IsGranted('ROLE_INGEST')]
    public function __invoke(Request $request, #[CurrentUser] UserInterface $source): Response
    {
        $sourceId = $source->getUserIdentifier();

        $limit = $this->logIngestLimiter->create($sourceId)->consume();
        if (!$limit->isAccepted()) {
            $response = ProblemDetails::forStatus(Response::HTTP_TOO_MANY_REQUESTS, 'Too many requests for this source.')->toResponse();
            $response->headers->set('Retry-After', (string) max(1, $limit->getRetryAfter()->getTimestamp() - time()));

            return $response;
        }

        try {
            $lines = $this->reader->read($request);
        } catch (InvalidPayload $e) {
            return ProblemDetails::forStatus($e->status, $e->getMessage())->toResponse();
        }

        return $this->json(
            ($this->ingestLogs)(new IngestLogs($sourceId, $lines)),
            Response::HTTP_ACCEPTED,
        );
    }
}
