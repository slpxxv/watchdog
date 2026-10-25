<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Shared\UI\Http;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Watchdog\Identity\Domain\Role\Exception\RoleNotFound;
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

    public function testItLeavesOtherExceptionsToSymfony(): void
    {
        self::assertNull($this->handle(new \RuntimeException('boom')));
    }

    private function handle(\Throwable $error): ?Response
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $error);
        (new DomainErrorListener(new DomainErrorMapper()))($event);

        return $event->getResponse();
    }
}
