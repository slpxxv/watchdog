<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Logging\Domain;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\Exception\InvalidSourceName;
use Watchdog\Logging\Domain\Source\Exception\SourceRevoked;
use Watchdog\Logging\Domain\Source\Source;
use Watchdog\Logging\Domain\Source\SourceId;
use Watchdog\Logging\Domain\Source\SourceToken;

final class SourceTest extends TestCase
{
    private function source(SourceToken $token, string $name = 'API'): Source
    {
        return Source::create(
            SourceId::fromString('00000000-0000-7000-c000-000000000001'),
            ProjectId::fromString('00000000-0000-7000-a000-000000000001'),
            $name,
            $token,
            new \DateTimeImmutable('2026-01-01'),
        );
    }

    private static function storedHash(Source $source): string
    {
        return (fn (): string => $this->tokenHash)->call($source);
    }

    public function testStoresOnlyTheHashAndPrefixOfTheToken(): void
    {
        $token = SourceToken::generate();
        $source = $this->source($token);

        self::assertSame($token->hash(), self::storedHash($source));
        self::assertSame($token->prefix(), $source->tokenPrefix());
        self::assertStringNotContainsString($token->value, serialize($source));
    }

    public function testRotationReplacesTheToken(): void
    {
        $old = SourceToken::generate();
        $new = SourceToken::generate();
        $source = $this->source($old);

        $source->rotateToken($new);

        self::assertSame($new->hash(), self::storedHash($source));
        self::assertSame($new->prefix(), $source->tokenPrefix());
    }

    public function testRevokeIsPermanentAndKeepsTheFirstDate(): void
    {
        $source = $this->source(SourceToken::generate());

        $source->revoke(new \DateTimeImmutable('2026-02-01'));
        $source->revoke(new \DateTimeImmutable('2026-03-01'));

        self::assertTrue($source->isRevoked());
        self::assertEquals(new \DateTimeImmutable('2026-02-01'), $source->revokedAt());
    }

    public function testRevokedSourceCannotRotate(): void
    {
        $source = $this->source(SourceToken::generate());
        $source->revoke(new \DateTimeImmutable('2026-02-01'));

        $this->expectException(SourceRevoked::class);
        $source->rotateToken(SourceToken::generate());
    }

    public function testNameIsTrimmed(): void
    {
        self::assertSame('API', $this->source(SourceToken::generate(), '  API  ')->name());
    }

    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith(['x', Source::MAX_NAME_LENGTH + 1])]
    public function testRejectsBlankOrTooLongName(string $char, int $times = 1): void
    {
        $this->expectException(InvalidSourceName::class);
        $this->source(SourceToken::generate(), str_repeat($char, $times));
    }
}
