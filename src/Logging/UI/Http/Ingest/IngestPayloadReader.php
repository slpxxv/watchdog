<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http\Ingest;

use Symfony\Component\HttpFoundation\Request;

/**
 * Reads an ingest request body into decoded lines. Accepts what shippers send: a JSON array of
 * objects, a single object, or NDJSON (one object per line), optionally gzip-compressed. The format
 * is told from the body, not the Content-Type, since shippers label it inconsistently.
 */
final readonly class IngestPayloadReader
{
    public const int MAX_BYTES = 2 * 1024 * 1024; // after decompression
    public const int MAX_LINES = 1000;
    private const int CHUNK = 64 * 1024;
    private const int DEPTH = 64;

    /**
     * @return list<array<array-key, mixed>>
     *
     * @throws InvalidPayload
     */
    public function read(Request $request): array
    {
        $length = $request->headers->get('Content-Length');
        if (null !== $length && (int) $length > self::MAX_BYTES) {
            throw InvalidPayload::tooLarge(\sprintf('The body exceeds %d bytes.', self::MAX_BYTES));
        }

        $body = $this->readCapped($request);

        $encoding = strtolower(trim((string) $request->headers->get('Content-Encoding', '')));
        $body = match ($encoding) {
            '', 'identity' => $body,
            'gzip', 'x-gzip' => $this->gunzip($body),
            default => throw InvalidPayload::unsupportedEncoding($encoding),
        };

        return $this->decode($body);
    }

    /**
     * Never trusts Content-Length: reads at most MAX_BYTES + 1 from the stream.
     */
    private function readCapped(Request $request): string
    {
        $stream = $request->getContent(true);
        $body = stream_get_contents($stream, self::MAX_BYTES + 1);
        if (false === $body) {
            throw InvalidPayload::malformed('The body could not be read.');
        }
        if (\strlen($body) > self::MAX_BYTES) {
            throw InvalidPayload::tooLarge(\sprintf('The body exceeds %d bytes.', self::MAX_BYTES));
        }

        return $body;
    }

    /**
     * Inflates in chunks and stops as soon as the output passes the limit, so a small "zip bomb"
     * cannot expand into gigabytes of memory.
     */
    private function gunzip(string $compressed): string
    {
        $inflate = inflate_init(\ZLIB_ENCODING_GZIP);
        if (false === $inflate) {
            throw InvalidPayload::malformed('The gzip body could not be decompressed.');
        }

        $out = '';
        foreach (str_split($compressed, self::CHUNK) as $chunk) {
            $part = @inflate_add($inflate, $chunk, \ZLIB_SYNC_FLUSH);
            if (false === $part) {
                throw InvalidPayload::malformed('The gzip body is corrupt.');
            }
            $out .= $part;
            if (\strlen($out) > self::MAX_BYTES) {
                throw InvalidPayload::tooLarge(\sprintf('The decompressed body exceeds %d bytes.', self::MAX_BYTES));
            }
        }

        return $out;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function decode(string $body): array
    {
        $trimmed = trim($body);
        if ('' === $trimmed) {
            return [];
        }

        $lines = match (true) {
            str_starts_with($trimmed, '[') => $this->jsonArray($trimmed),
            // A single object, possibly pretty-printed over several lines.
            null !== ($single = $this->singleObject($trimmed)) => [$single],
            default => $this->ndjson($trimmed),
        };

        if (\count($lines) > self::MAX_LINES) {
            throw InvalidPayload::tooLarge(\sprintf('A request may carry at most %d lines.', self::MAX_LINES));
        }

        $objects = [];
        foreach ($lines as $index => $line) {
            // JSON objects decode to arrays; a non-empty list was a JSON array, not an object.
            if (!\is_array($line) || ([] !== $line && array_is_list($line))) {
                throw InvalidPayload::wrongShape(\sprintf('Line %d must be a JSON object.', $index + 1));
            }
            $objects[] = $line;
        }

        return $objects;
    }

    /**
     * @return list<mixed>
     */
    private function jsonArray(string $body): array
    {
        $decoded = $this->json($body, 'The body');
        if (!\is_array($decoded) || !array_is_list($decoded)) {
            throw InvalidPayload::wrongShape('The body must be a JSON array of objects.');
        }

        return $decoded;
    }

    /**
     * @return list<mixed> stops one past MAX_LINES; the caller rejects that
     */
    private function ndjson(string $body): array
    {
        $lines = [];
        foreach (preg_split('/\r?\n/', $body) ?: [] as $number => $raw) {
            if ('' === trim($raw)) {
                continue;
            }
            $lines[] = $this->json($raw, \sprintf('Line %d', $number + 1));
            if (\count($lines) > self::MAX_LINES) {
                break;
            }
        }

        return $lines;
    }

    /**
     * @return ?array<array-key, mixed> the body as one JSON object, or null if it is not one
     */
    private function singleObject(string $body): ?array
    {
        try {
            $decoded = json_decode($body, true, self::DEPTH, \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            return null; // several objects: NDJSON
        }

        return \is_array($decoded) && ([] === $decoded || !array_is_list($decoded)) ? $decoded : null;
    }

    private function json(string $raw, string $what): mixed
    {
        try {
            // Invalid UTF-8 is replaced rather than rejected: the line still gets stored.
            return json_decode($raw, true, self::DEPTH, \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw InvalidPayload::malformed(\sprintf('%s is not valid JSON: %s.', $what, $e->getMessage()));
        }
    }
}
