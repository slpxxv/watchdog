<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Shared\UI\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Watchdog\Shared\UI\Twig\PolishPlural;

final class PolishPluralTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function counts(): iterable
    {
        yield 'one' => [1, 'linia'];
        yield 'few' => [2, 'linie'];
        yield 'few 24' => [24, 'linie'];
        yield 'many 5' => [5, 'linii'];
        yield 'many 0' => [0, 'linii'];
        yield 'teens are many' => [12, 'linii'];
        yield 'teens 14' => [14, 'linii'];
        yield 'hundred and two' => [102, 'linie'];
        yield 'twenty one is many' => [21, 'linii'];
    }

    #[DataProvider('counts')]
    public function testItPicksTheFormOfTheCount(int $count, string $expected): void
    {
        self::assertSame($expected, (new PolishPlural())($count, 'linia', 'linie', 'linii'));
    }
}
