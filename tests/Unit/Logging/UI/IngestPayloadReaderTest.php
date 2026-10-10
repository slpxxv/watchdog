<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Logging\UI;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Watchdog\Logging\UI\Http\Ingest\IngestPayloadReader;
use Watchdog\Logging\UI\Http\Ingest\InvalidPayload;

final class IngestPayloadReaderTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     *
     * @return list<array<array-key, mixed>>
     */
    private function read(string $body, array $headers = []): array
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return (new IngestPayloadReader())->read(Request::create('/', 'POST', server: $server, content: $body));
    }

    /**
     * @param array<string, string> $headers
     */
    private function rejectionStatus(string $body, array $headers = []): int
    {
        try {
            $this->read($body, $headers);
        } catch (InvalidPayload $e) {
            return $e->status;
        }

        return 200;
    }

    public function testFormats(): void
    {
        self::assertSame([['message' => 'a'], ['message' => 'b']], $this->read('[{"message":"a"},{"message":"b"}]'));
        self::assertSame([['message' => 'a']], $this->read('{"message":"a"}'));
        self::assertSame([['message' => 'a']], $this->read("{\n  \"message\": \"a\"\n}"), 'pretty-printed single object');
        self::assertSame([['message' => 'a'], ['message' => 'b']], $this->read("{\"message\":\"a\"}\r\n\n{\"message\":\"b\"}\n"), 'NDJSON, blank lines skipped');
        self::assertSame([], $this->read('  '));
        self::assertSame([[]], $this->read('[{}]'), 'an empty object is a line');
    }

    public function testGzip(): void
    {
        $body = (string) gzencode("{\"message\":\"a\"}\n{\"message\":\"b\"}");

        self::assertCount(2, $this->read($body, ['Content-Encoding' => 'gzip']));
    }

    public function testZipBombStopsAtTheLimit(): void
    {
        // ~10 MB of zeros compress to ~10 KB: rejected after inflating just past 2 MB.
        $bomb = (string) gzencode('["'.str_repeat('0', 10 * 1024 * 1024).'"]', 9);
        self::assertLessThan(64 * 1024, \strlen($bomb));

        self::assertSame(413, $this->rejectionStatus($bomb, ['Content-Encoding' => 'gzip']));
    }

    /**
     * @return iterable<string, array{string, int, 2?: array<string, string>}>
     */
    public static function rejected(): iterable
    {
        yield 'broken JSON' => ['[{"message":', 400];
        yield 'broken NDJSON line' => ["{\"a\":1}\n{oops}", 400];
        yield 'corrupt gzip' => ['not gzip', 400, ['Content-Encoding' => 'gzip']];
        yield 'too deep' => [str_repeat('[', 100).str_repeat(']', 100), 400];
        yield 'array of strings' => ['["a","b"]', 422];
        yield 'nested array line' => ['[[1,2]]', 422];
        yield 'object with a list as body' => ['[1]', 422];
        yield 'brotli' => ['{}', 415, ['Content-Encoding' => 'br']];
        yield 'declared too large' => ['{}', 413, ['Content-Length' => (string) (IngestPayloadReader::MAX_BYTES + 1)]];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('rejected')]
    public function testRejects(string $body, int $status, array $headers = []): void
    {
        self::assertSame($status, $this->rejectionStatus($body, $headers));
    }

    public function testBodyLargerThanTheLimitIsRejectedWithoutTrustingContentLength(): void
    {
        self::assertSame(413, $this->rejectionStatus('["'.str_repeat('x', IngestPayloadReader::MAX_BYTES).'"]'));
    }

    public function testLineLimit(): void
    {
        $line = '{"m":1}';

        self::assertCount(IngestPayloadReader::MAX_LINES, $this->read('['.implode(',', array_fill(0, IngestPayloadReader::MAX_LINES, $line)).']'));
        self::assertSame(413, $this->rejectionStatus('['.implode(',', array_fill(0, IngestPayloadReader::MAX_LINES + 1, $line)).']'));
        self::assertSame(413, $this->rejectionStatus(implode("\n", array_fill(0, IngestPayloadReader::MAX_LINES + 1, $line))));
    }

    public function testInvalidUtf8IsSubstitutedNotRejected(): void
    {
        $lines = $this->read("{\"message\":\"bad \xC3\x28 byte\"}");

        self::assertSame("bad \u{FFFD}( byte", $lines[0]['message']);
    }
}
