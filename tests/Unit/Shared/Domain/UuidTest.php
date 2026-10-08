<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain;

use App\Identity\Domain\Role\RoleId;
use App\Identity\Domain\User\UserId;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    private const string ID = '01A11C9A-0C58-715B-A7C3-CADEC5D3F36A';

    public function testItNormalizesToLowercase(): void
    {
        $id = UserId::fromString(self::ID);

        self::assertSame(strtolower(self::ID), $id->value);
        self::assertSame(strtolower(self::ID), (string) $id);
    }

    #[TestWith([''])]
    #[TestWith(['not-a-uuid'])]
    #[TestWith(['01a11c9a0c58715ba7c3cadec5d3f36a'])]
    #[TestWith(['01a11c9a-0c58-715b-a7c3-cadec5d3f36a '])]
    public function testItRejectsInvalid(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UserId::fromString($value);
    }

    public function testEqualityIsByValueWithinOneIdType(): void
    {
        self::assertTrue(UserId::fromString(self::ID)->equals(UserId::fromString(strtolower(self::ID))));
        self::assertFalse(UserId::fromString(self::ID)->equals(UserId::fromString('01a11c9a-0c58-715b-a7c3-cadec5d3f36b')));
    }

    public function testDifferentIdTypesAreNeverEqual(): void
    {
        self::assertFalse(UserId::fromString(self::ID)->equals(RoleId::fromString(self::ID)));
    }
}
