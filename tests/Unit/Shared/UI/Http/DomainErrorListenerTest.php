<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Shared\UI\Http;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Watchdog\Identity\Domain\Role\Exception\RoleNotFound;
use Watchdog\Project\Domain\Exception\InvalidProjectName;
use Watchdog\Shared\UI\Http\DomainErrorListener;
use Watchdog\Shared\UI\Http\Problem\DomainErrorMapper;
use Watchdog\Shared\UI\Http\Problem\ProblemDetails;

final class DomainErrorListenerTest extends TestCase
{
    public function testItAnswersDomainErrorWithProblemDetails(): void
    {
        $response = $this->handle(RoleNotFound::withCode('editor'));

        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame(ProblemDetails::CONTENT_TYPE, $response->headers->get('Content-Type'));
        self::assertStringContainsString('"messageKey":"identity.role.not_found"', (string) $response->getContent());
    }

    public function testPagesGetTheUsualErrorPagesNotProblemDetails(): void
    {
        $event = $this->event(RoleNotFound::withCode('editor'), '/projects');
        (new DomainErrorListener(new DomainErrorMapper()))($event);

        self::assertNull($event->getResponse());
        self::assertInstanceOf(NotFoundHttpException::class, $event->getThrowable());
    }

    public function testPagesLeaveOtherDomainErrorsToTheAction(): void
    {
        $error = InvalidProjectName::for('');
        $event = $this->event($error, '/projects');
        (new DomainErrorListener(new DomainErrorMapper()))($event);

        self::assertNull($event->getResponse());
        self::assertSame($error, $event->getThrowable());
    }

    public function testItLeavesOtherExceptionsToSymfony(): void
    {
        self::assertNull($this->handle(new \RuntimeException('boom')));
    }

    private function handle(\Throwable $error): ?Response
    {
        $event = $this->event($error, '/api/roles');
        (new DomainErrorListener(new DomainErrorMapper()))($event);

        return $event->getResponse();
    }

    private function event(\Throwable $error, string $path): ExceptionEvent
    {
        return new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create($path), HttpKernelInterface::MAIN_REQUEST, $error);
    }
}
