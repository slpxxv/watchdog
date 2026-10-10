<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Logging\Domain;

use PHPUnit\Framework\TestCase;
use Watchdog\Logging\Domain\Source\SourceToken;

final class SourceTokenTest extends TestCase
{
    public function testGeneratedTokenIsPrefixedUrlSafeAndUnique(): void
    {
        $token = SourceToken::generate();

        self::assertMatchesRegularExpression('/^wd_[A-Za-z0-9_-]{43}$/', $token->value, '32 random bytes, base64url without padding');
        self::assertNotSame($token->value, SourceToken::generate()->value);
    }

    public function testHashIsSha256OfTheToken(): void
    {
        $token = SourceToken::generate();

        self::assertSame(hash('sha256', $token->value), $token->hash());
        self::assertSame($token->hash(), SourceToken::hashOf($token->value));
    }

    public function testPrefixIsTooShortToUse(): void
    {
        $token = SourceToken::generate();

        self::assertSame(substr($token->value, 0, 8), $token->prefix());
        self::assertStringStartsWith('wd_', $token->prefix());
    }
}
