<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Log;

/**
 * Keyset position in a newest-first listing: the (timestamp, id) of the last line already returned.
 */
final readonly class LogCursor
{
    private const string FORMAT = 'Y-m-d\TH:i:s.uP';

    public function __construct(
        public \DateTimeImmutable $timestamp,
        public int $id,
    ) {
    }

    public static function after(LogEntry $entry): self
    {
        return new self($entry->timestamp, $entry->id ?? throw new \LogicException('Only stored entries have a position.'));
    }

    public function encode(): string
    {
        return rtrim(strtr(base64_encode($this->timestamp->format(self::FORMAT).'|'.$this->id), '+/', '-_'), '=');
    }

    /**
     * @throws \InvalidArgumentException for anything that is not a cursor this class produced
     */
    public static function decode(string $encoded): self
    {
        $raw = base64_decode(strtr($encoded, '-_', '+/'), true);
        $parts = false === $raw ? [] : explode('|', $raw);
        $timestamp = 2 === \count($parts) ? \DateTimeImmutable::createFromFormat(self::FORMAT, $parts[0]) : false;

        if (false === $timestamp || !ctype_digit($parts[1])) {
            throw new \InvalidArgumentException('Invalid log cursor.');
        }

        return new self($timestamp, (int) $parts[1]);
    }
}
