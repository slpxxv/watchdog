<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Logging\UI;

use PHPUnit\Framework\TestCase;
use Watchdog\Logging\UI\Twig\LogText;

final class LogTextTest extends TestCase
{
    public function testItStripsTerminalControlCodesButKeepsTabsAndNewlines(): void
    {
        self::assertSame("ERROR: disk full\tnow\nnext", (new LogText())->plain("\x1b[31mERROR\x1b[0m: disk\x07 full\tnow\nnext"));
    }

    public function testMarkupAndUnicodeAreLeftForTheTemplateToEscape(): void
    {
        self::assertSame('<script>alert(1)</script> zażółć', (new LogText())->plain('<script>alert(1)</script> zażółć'));
    }

    public function testFieldsShowTheFirstFewTopLevelKeysShortened(): void
    {
        self::assertSame(
            [['user', '7'], ['path', '/x'], ['tags', '["a"]'], ['long', str_repeat('y', 59).'…']],
            (new LogText())->fields(['user' => 7, 'path' => '/x', 'tags' => ['a'], 'long' => str_repeat('y', 80), 'extra' => 1]),
        );
        self::assertSame([], (new LogText())->fields([]));
    }

    public function testAnEmptyContextIsShownAsAnObject(): void
    {
        self::assertSame('{}', (new LogText())->json([]));
    }
}
