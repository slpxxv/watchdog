<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\User\Email;
use App\Identity\Domain\User\Exception\InvalidEmail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testItNormalizes(): void
    {
        self::assertSame('john@example.com', Email::fromString('  John@Example.COM ')->value);
    }

    #[DataProvider('invalid')]
    public function testItRejectsInvalid(string $value): void
    {
        $this->expectException(InvalidEmail::class);
        Email::fromString($value);
    }

    /** @return iterable<array{string}> */
    public static function invalid(): iterable
    {
        yield [''];
        yield ['not-an-email'];
        yield ["x' OR 1=1 --@example.com"];
        yield [str_repeat('a', 180).'@example.com'];
    }
}
