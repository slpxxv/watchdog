<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Logging\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Watchdog\Logging\Domain\Log\LogLevel;

final class LogLevelParseTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, ?LogLevel}>
     */
    public static function levels(): iterable
    {
        yield 'psr-3 name' => ['warning', LogLevel::Warning];
        yield 'any case, padded' => ['  ERROR ', LogLevel::Error];
        yield 'warn' => ['warn', LogLevel::Warning];
        yield 'fatal' => ['fatal', LogLevel::Critical];
        yield 'trace' => ['trace', LogLevel::Debug];
        yield 'panic' => ['panic', LogLevel::Emergency];
        yield 'monolog 200' => [200, LogLevel::Info];
        yield 'monolog 550' => [550, LogLevel::Alert];
        yield 'monolog as string' => ['400', LogLevel::Error];
        yield 'syslog 0 is emergency' => [0, LogLevel::Emergency];
        yield 'syslog 7 is debug' => [7, LogLevel::Debug];
        yield 'syslog 4 is warning' => [4, LogLevel::Warning];
        yield 'unknown name' => ['loud', null];
        yield 'out of range number' => [42, null];
        yield 'float' => [3.5, null];
        yield 'null' => [null, null];
    }

    #[DataProvider('levels')]
    public function testParse(mixed $raw, ?LogLevel $expected): void
    {
        self::assertSame($expected, LogLevel::parse($raw));
    }
}
