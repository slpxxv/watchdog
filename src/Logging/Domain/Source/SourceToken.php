<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Source;

/**
 * Bearer token a log shipper sends with each request. Only its SHA-256 hash is stored: the token
 * carries 256 bits of entropy, so an unsalted hash cannot be brute-forced and can be looked up directly.
 */
final readonly class SourceToken
{
    public const string PREFIX = 'wd_';
    private const int BYTES = 32;
    private const int VISIBLE_CHARS = 8;

    private function __construct(
        #[\SensitiveParameter] public string $value,
    ) {
    }

    public static function generate(): self
    {
        return new self(self::PREFIX.rtrim(strtr(base64_encode(random_bytes(self::BYTES)), '+/', '-_'), '='));
    }

    /**
     * Hash of a presented token, for lookup; does not validate the format.
     */
    public static function hashOf(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    public function hash(): string
    {
        return self::hashOf($this->value);
    }

    /**
     * Enough of the token to recognise it in a list, never enough to use it.
     */
    public function prefix(): string
    {
        return substr($this->value, 0, self::VISIBLE_CHARS);
    }
}
