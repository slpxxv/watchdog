<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Shared\UI\Http\Problem;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Watchdog\Shared\UI\Http\Problem\ProblemDetails;

final class ProblemDetailsTest extends TestCase
{
    public function testForStatusUsesStandardTitleAndDefaultType(): void
    {
        $problem = ProblemDetails::forStatus(404, 'Role "x" not found.');

        self::assertSame(['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404, 'detail' => 'Role "x" not found.'], $problem->toArray());
    }

    public function testDetailIsOmittedWhenAbsent(): void
    {
        self::assertArrayNotHasKey('detail', ProblemDetails::forStatus(409)->toArray());
    }

    public function testExtensionsCannotOverrideStandardMembers(): void
    {
        $body = ProblemDetails::forStatus(422, 'Broken rule.', ['status' => 200, 'title' => 'OK', 'messageKey' => 'a.b'])->toArray();

        self::assertSame(422, $body['status']);
        self::assertSame('Unprocessable Content', $body['title']);
        self::assertSame('a.b', $body['messageKey']);
    }

    public function testToResponse(): void
    {
        $response = ProblemDetails::forStatus(403, 'Nope.', ['messageKey' => 'acl.denied'])->toResponse();

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(ProblemDetails::CONTENT_TYPE, $response->headers->get('Content-Type'));
        self::assertJsonStringEqualsJsonString(
            '{"type":"about:blank","title":"Forbidden","status":403,"detail":"Nope.","messageKey":"acl.denied"}',
            (string) $response->getContent(),
        );
    }

    #[TestWith([200])]
    #[TestWith([302])]
    #[TestWith([600])]
    public function testItRejectsNonErrorStatus(int $status): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProblemDetails($status, 'Nope');
    }
}
