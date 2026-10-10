<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command\IngestLogs;

use Watchdog\Logging\Domain\Log\LogEntry;
use Watchdog\Logging\Domain\Log\LogLevel;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\SourceId;

/**
 * Turns one decoded line into a LogEntry, fixing whatever it can instead of rejecting it:
 * a log must never be lost over a detail. Every fix is recorded in the line's context.
 */
final readonly class LineNormalizer
{
    public const int MAX_MESSAGE_BYTES = 8 * 1024;
    public const int MAX_CONTEXT_BYTES = 16 * 1024;
    public const string WINDOW_PAST = '-7 days'; // partitions exist only for this window
    public const string WINDOW_FUTURE = '+1 hour';

    // The names common shippers use: Vector "message", Fluent Bit "log", Monolog "datetime"/"level_name".
    private const array MESSAGE_KEYS = ['message', 'msg', 'log'];
    private const array LEVEL_KEYS = ['level', 'severity', 'level_name', 'lvl'];
    private const array TIMESTAMP_KEYS = ['timestamp', '@timestamp', 'time', 'datetime', 'ts'];
    private const string REPLACEMENT = "\u{FFFD}";

    /**
     * @param array<array-key, mixed> $line
     */
    public function normalize(array $line, ProjectId $projectId, SourceId $sourceId, \DateTimeImmutable $now): NormalizedLine
    {
        $fixes = [];

        $message = $this->message(self::take($line, self::MESSAGE_KEYS));
        $level = $this->level(self::takeAll($line, self::LEVEL_KEYS), $fixes);
        $timestamp = $this->timestamp(self::take($line, self::TIMESTAMP_KEYS), $now, $fixes);

        $explicit = $line['context'] ?? null;
        unset($line['context']);
        // JSON objects decode to arrays; an empty {} is an empty array, which still counts as an object.
        $isObject = \is_array($explicit) && ([] === $explicit || !array_is_list($explicit));
        // Anything else the shipper sent (host, service, Monolog's channel/extra...) is kept as context.
        $context = $isObject ? array_merge($line, $explicit) : $line;
        if (null !== $explicit && !$isObject) {
            $context['context'] = $explicit;
        }

        if (str_contains($message, "\0")) {
            // pdo_pgsql would silently cut the text at a NUL byte.
            $message = str_replace("\0", self::REPLACEMENT, $message);
            $fixes['_nul_replaced'] = true;
        }
        if (\strlen($message) > self::MAX_MESSAGE_BYTES) {
            $message = mb_strcut($message, 0, self::MAX_MESSAGE_BYTES, 'UTF-8');
            $fixes['_truncated'] = true;
        }

        $context = $this->scrub($context, $fixes);
        $size = \strlen(json_encode($context, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));
        if ($size > self::MAX_CONTEXT_BYTES) {
            $context = ['_dropped' => 'context too large', '_size' => $size];
        }
        $wasFixed = [] !== $fixes || $size > self::MAX_CONTEXT_BYTES;

        /** @var array<string, mixed> $context */
        $context = array_merge($context, $fixes);

        return new NormalizedLine(
            new LogEntry(
                projectId: $projectId,
                sourceId: $sourceId,
                timestamp: $timestamp,
                receivedAt: $now,
                level: $level,
                message: $message,
                context: $context,
            ),
            $wasFixed,
        );
    }

    private function message(mixed $raw): string
    {
        return match (true) {
            \is_string($raw) => mb_scrub($raw, 'UTF-8'),
            null === $raw => '',
            \is_bool($raw) => $raw ? 'true' : 'false',
            \is_int($raw), \is_float($raw) => (string) $raw,
            default => json_encode($raw, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
        };
    }

    /**
     * @param list<mixed>          $candidates every level-like field the line had, in key order
     * @param array<string, mixed> $fixes
     */
    private function level(array $candidates, array &$fixes): LogLevel
    {
        foreach ($candidates as $candidate) {
            if (null !== $level = LogLevel::parse($candidate)) {
                return $level;
            }
        }
        if ([] !== $candidates) {
            $fixes['_original_level'] = $candidates[0];
        }

        return LogLevel::Info;
    }

    /**
     * @param array<string, mixed> $fixes
     */
    private function timestamp(mixed $raw, \DateTimeImmutable $now, array &$fixes): \DateTimeImmutable
    {
        if (null === $raw) {
            return $now;
        }

        $parsed = self::parseTimestamp($raw);
        if (null === $parsed || $parsed < $now->modify(self::WINDOW_PAST) || $parsed > $now->modify(self::WINDOW_FUTURE)) {
            $fixes['_original_timestamp'] = $raw;

            return $now;
        }

        return $parsed;
    }

    private static function parseTimestamp(mixed $raw): ?\DateTimeImmutable
    {
        if (\is_int($raw) || \is_float($raw)) {
            $seconds = $raw > 1e12 ? $raw / 1000 : $raw; // epoch milliseconds or seconds

            return \DateTimeImmutable::createFromFormat('U.u', \sprintf('%.6F', $seconds)) ?: null;
        }
        if (!\is_string($raw) || '' === trim($raw)) {
            return null;
        }
        if (is_numeric($raw)) {
            return self::parseTimestamp(+$raw);
        }

        try {
            return new \DateTimeImmutable($raw, new \DateTimeZone('UTC')); // no offset means UTC
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Replaces NUL bytes in keys and strings: jsonb rejects them and would fail the whole batch.
     *
     * @param array<array-key, mixed> $value
     * @param array<string, mixed>    $fixes
     *
     * @return array<array-key, mixed>
     */
    private function scrub(array $value, array &$fixes): array
    {
        $clean = [];
        foreach ($value as $key => $item) {
            if (\is_string($key) && str_contains($key, "\0")) {
                $key = str_replace("\0", self::REPLACEMENT, $key);
                $fixes['_nul_replaced'] = true;
            }
            if (\is_string($item) && str_contains($item, "\0")) {
                $item = str_replace("\0", self::REPLACEMENT, $item);
                $fixes['_nul_replaced'] = true;
            } elseif (\is_array($item)) {
                $item = $this->scrub($item, $fixes);
            }
            $clean[$key] = $item;
        }

        return $clean;
    }

    /**
     * Removes and returns the first present key.
     *
     * @param array<array-key, mixed> $line
     * @param list<string>            $keys
     */
    private static function take(array &$line, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (\array_key_exists($key, $line)) {
                $value = $line[$key];
                unset($line[$key]);

                return $value;
            }
        }

        return null;
    }

    /**
     * Removes every present key and returns their values in key order.
     *
     * @param array<array-key, mixed> $line
     * @param list<string>            $keys
     *
     * @return list<mixed>
     */
    private static function takeAll(array &$line, array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            if (\array_key_exists($key, $line)) {
                $values[] = $line[$key];
                unset($line[$key]);
            }
        }

        return $values;
    }
}
