<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Logging\Application;

use PHPUnit\Framework\TestCase;
use Watchdog\Logging\Application\Command\IngestLogs\LineNormalizer;
use Watchdog\Logging\Application\Command\IngestLogs\NormalizedLine;
use Watchdog\Logging\Domain\Log\LogLevel;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\SourceId;

final class LineNormalizerTest extends TestCase
{
    private const string NOW = '2026-10-10T12:00:00.000000+00:00';

    /**
     * @param array<array-key, mixed> $line
     */
    private function normalize(array $line): NormalizedLine
    {
        return (new LineNormalizer())->normalize(
            $line,
            ProjectId::fromString('00000000-0000-7000-a000-000000000001'),
            SourceId::fromString('00000000-0000-7000-c000-000000000001'),
            new \DateTimeImmutable(self::NOW),
        );
    }

    public function testCleanLinePassesUntouched(): void
    {
        $line = $this->normalize(['timestamp' => '2026-10-10T11:59:00.5Z', 'level' => 'error', 'message' => 'Boom', 'context' => ['order' => 5]]);

        self::assertFalse($line->wasFixed);
        self::assertSame(LogLevel::Error, $line->entry->level);
        self::assertSame('Boom', $line->entry->message);
        self::assertSame(['order' => 5], $line->entry->context);
        self::assertSame('2026-10-10T11:59:00.500000+00:00', $line->entry->timestamp->format('Y-m-d\TH:i:s.uP'));
        self::assertSame(self::NOW, $line->entry->receivedAt->format('Y-m-d\TH:i:s.uP'));
    }

    public function testMissingFieldsAreNotFixes(): void
    {
        $line = $this->normalize([]);

        self::assertFalse($line->wasFixed);
        self::assertSame('', $line->entry->message);
        self::assertSame(LogLevel::Info, $line->entry->level);
        self::assertSame(self::NOW, $line->entry->timestamp->format('Y-m-d\TH:i:s.uP'));
    }

    public function testReadsTheFieldNamesShippersUse(): void
    {
        // Fluent Bit: "log"; Monolog: "datetime", numeric "level" plus "level_name", "channel", "extra".
        $fluentBit = $this->normalize(['log' => 'from fluent bit', 'time' => '2026-10-10 11:00:00']);
        $monolog = $this->normalize(['message' => 'from monolog', 'level' => 300, 'level_name' => 'WARNING', 'datetime' => '2026-10-10T13:30:00+02:00', 'channel' => 'app', 'extra' => ['ip' => '10.0.0.1']]);

        self::assertSame('from fluent bit', $fluentBit->entry->message);
        self::assertSame('2026-10-10 11:00:00', $fluentBit->entry->timestamp->format('Y-m-d H:i:s'), 'no offset means UTC');
        self::assertSame(LogLevel::Warning, $monolog->entry->level);
        self::assertSame('2026-10-10T11:30:00+00:00', $monolog->entry->timestamp->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:sP'));
        self::assertSame(['channel' => 'app', 'extra' => ['ip' => '10.0.0.1']], $monolog->entry->context, 'level fields are consumed, the rest is context');
        self::assertFalse($monolog->wasFixed);
    }

    public function testExtraTopLevelFieldsBecomeContextAndExplicitContextWins(): void
    {
        $line = $this->normalize(['message' => 'm', 'host' => 'web-1', 'env' => 'staging', 'context' => ['env' => 'prod']]);

        self::assertSame(['host' => 'web-1', 'env' => 'prod'], $line->entry->context);
    }

    public function testEpochSecondsAndMilliseconds(): void
    {
        $seconds = (new \DateTimeImmutable('2026-10-10T11:00:00Z'))->getTimestamp();

        self::assertSame('2026-10-10 11:00:00', $this->normalize(['timestamp' => $seconds])->entry->timestamp->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-10 11:00:00.250', $this->normalize(['timestamp' => $seconds * 1000 + 250])->entry->timestamp->format('Y-m-d H:i:s.v'));
        self::assertSame('2026-10-10 11:00:00', $this->normalize(['timestamp' => (string) $seconds])->entry->timestamp->format('Y-m-d H:i:s'));
    }

    public function testUnreadableOrOutOfWindowTimestampFallsBackToNow(): void
    {
        foreach (['yesterday-ish', '2026-09-01T00:00:00Z', '2026-10-10T14:00:00Z', ['nested']] as $raw) {
            $line = $this->normalize(['message' => 'm', 'timestamp' => $raw]);

            self::assertTrue($line->wasFixed, json_encode($raw, \JSON_THROW_ON_ERROR));
            self::assertSame(self::NOW, $line->entry->timestamp->format('Y-m-d\TH:i:s.uP'));
            self::assertSame($raw, $line->entry->context['_original_timestamp']);
        }

        self::assertFalse($this->normalize(['timestamp' => '2026-10-03T12:00:01Z'])->wasFixed, 'just inside 7 days back');
    }

    public function testUnknownLevelBecomesInfoAndIsRecorded(): void
    {
        $line = $this->normalize(['message' => 'm', 'level' => 'LOUD']);

        self::assertTrue($line->wasFixed);
        self::assertSame(LogLevel::Info, $line->entry->level);
        self::assertSame('LOUD', $line->entry->context['_original_level']);
    }

    public function testNonStringMessagesAreStringified(): void
    {
        self::assertSame('42', $this->normalize(['message' => 42])->entry->message);
        self::assertSame('false', $this->normalize(['message' => false])->entry->message);
        self::assertSame('{"a":"ż"}', $this->normalize(['message' => ['a' => 'ż']])->entry->message);
    }

    public function testLongMessageIsCutOnACharacterBoundary(): void
    {
        $line = $this->normalize(['message' => str_repeat('ż', LineNormalizer::MAX_MESSAGE_BYTES)]); // 2 bytes each

        self::assertTrue($line->wasFixed);
        self::assertTrue($line->entry->context['_truncated']);
        self::assertSame(LineNormalizer::MAX_MESSAGE_BYTES, \strlen($line->entry->message));
        self::assertTrue(mb_check_encoding($line->entry->message, 'UTF-8'));
    }

    public function testNulBytesAreReplacedEverywhere(): void
    {
        $line = $this->normalize(['message' => "a\0b", 'context' => ["k\0" => ['v' => "x\0y"]]]);

        self::assertTrue($line->wasFixed);
        self::assertSame("a\u{FFFD}b", $line->entry->message);
        self::assertSame(["k\u{FFFD}" => ['v' => "x\u{FFFD}y"], '_nul_replaced' => true], $line->entry->context);
    }

    public function testHugeContextIsDropped(): void
    {
        $line = $this->normalize(['message' => 'm', 'context' => ['blob' => str_repeat('x', LineNormalizer::MAX_CONTEXT_BYTES)]]);

        self::assertTrue($line->wasFixed);
        self::assertSame('context too large', $line->entry->context['_dropped']);
        self::assertSame('m', $line->entry->message);
    }

    public function testNonObjectContextIsKeptUnderItsKey(): void
    {
        self::assertSame(['context' => ['a', 'b']], $this->normalize(['context' => ['a', 'b']])->entry->context);
        self::assertSame([], $this->normalize(['context' => []])->entry->context, 'an empty {} is an object');
    }
}
