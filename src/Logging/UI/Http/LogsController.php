<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http;

use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Watchdog\Logging\Application\Dto\LogPageDto;
use Watchdog\Logging\Application\Query\ListSources;
use Watchdog\Logging\Application\Query\SearchLogs;
use Watchdog\Logging\Application\Query\TailLogs;
use Watchdog\Logging\UI\Http\Request\LogsQuery;
use Watchdog\Project\Application\Query\GetProject;
use Watchdog\Shared\Domain\Error\DomainError;
use Watchdog\Shared\Domain\Permission;
use Watchdog\Shared\UI\Http\FlashesDomainErrors;

#[IsGranted(Permission::LogView->value)]
final class LogsController extends AbstractController
{
    use FlashesDomainErrors;

    private const string MORE_FRAME = 'more-';

    public function __construct(
        private readonly SearchLogs $searchLogs,
        private readonly TailLogs $tailLogs,
        private readonly ListSources $listSources,
        private readonly GetProject $getProject,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Search: the lines of a time range, newest first. "Załaduj starsze" is a Turbo frame that
     * fetches the next page and appends it below.
     */
    #[Route(
        '/projects/{projectId}/events',
        name: 'log_events',
        requirements: ['projectId' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function events(
        string $projectId,
        Request $request,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY, mapWhenEmpty: true)] LogsQuery $query,
    ): Response {
        $frame = $request->headers->get('Turbo-Frame');
        $from = $query->from ?? $this->clock->now()->modify(LogsQuery::RANGES[$query->range]['modifier']);

        $page = null;
        $status = Response::HTTP_OK;
        try {
            $page = ($this->searchLogs)(
                projectId: $projectId,
                filters: $query->filters(),
                from: $from,
                cursor: '' === $query->cursor ? null : $query->cursor,
            );
        } catch (DomainError $error) {
            $this->flashDomainError($error);
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        if (null !== $frame && str_starts_with($frame, self::MORE_FRAME)) {
            return $this->render('logging/_older.html.twig', $this->olderContext($projectId, $query, $page, $frame));
        }

        return $this->render('logging/events.html.twig', [
            'project' => ($this->getProject)($projectId),
            'sources' => ($this->listSources)($projectId),
            'query' => $query,
            'ranges' => LogsQuery::RANGES,
            'now' => $this->clock->now(),
        ] + $this->olderContext($projectId, $query, $page, null), new Response(status: $status));
    }

    /**
     * Live tail: the page starts with the recent lines, then the tail controller polls log_tail.
     */
    #[Route(
        '/projects/{projectId}/live',
        name: 'log_live',
        requirements: ['projectId' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function live(
        string $projectId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY, mapWhenEmpty: true)] LogsQuery $query,
    ): Response {
        $tail = null;
        $status = Response::HTTP_OK;
        try {
            $tail = ($this->tailLogs)(projectId: $projectId, filters: $query->filters());
        } catch (DomainError $error) {
            $this->flashDomainError($error);
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return $this->render('logging/live.html.twig', [
            'project' => ($this->getProject)($projectId),
            'sources' => ($this->listSources)($projectId),
            'query' => $query,
            'tail' => $tail,
            'tailUrl' => $this->generateUrl('log_tail', ['projectId' => $projectId] + $query->parameters()),
        ], new Response(status: $status));
    }

    /**
     * What the tail controller polls: only the new lines as list items, and the last id in a header.
     * The server repeats recent lines on purpose (a row committed late can carry a lower id), so the
     * client keeps lines by id and drops the duplicates.
     */
    #[Route(
        '/projects/{projectId}/logs/tail',
        name: 'log_tail',
        requirements: ['projectId' => Requirement::UUID],
        methods: ['GET'],
    )]
    public function tail(
        string $projectId,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY, mapWhenEmpty: true)] LogsQuery $query,
    ): Response {
        $tail = ($this->tailLogs)(projectId: $projectId, afterId: $query->after, filters: $query->filters());

        $response = $this->render('logging/_lines.html.twig', ['lines' => $tail->items]);
        $response->headers->set('X-Tail-Last-Id', (string) $tail->lastId);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function olderContext(string $projectId, LogsQuery $query, ?LogPageDto $page, ?string $frame): array
    {
        $olderUrl = null;
        $nextFrame = null;
        if (null !== $page && null !== $page->nextCursor) {
            $nextFrame = self::MORE_FRAME.substr(md5($page->nextCursor), 0, 12);
            $olderUrl = $this->generateUrl('log_events', [
                'projectId' => $projectId,
                'range' => $query->range,
                'cursor' => $page->nextCursor,
                'from' => $page->from,
            ] + $query->parameters());
        }

        return ['frame' => $frame, 'page' => $page, 'nextFrame' => $nextFrame, 'olderUrl' => $olderUrl];
    }
}
