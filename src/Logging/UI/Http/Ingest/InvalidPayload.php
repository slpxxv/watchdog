<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http\Ingest;

use Symfony\Component\HttpFoundation\Response;

/**
 * A request body the ingest endpoint cannot accept; carries the HTTP status to answer with.
 */
final class InvalidPayload extends \RuntimeException
{
    private function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message);
    }

    public static function malformed(string $detail): self
    {
        return new self($detail, Response::HTTP_BAD_REQUEST);
    }

    public static function wrongShape(string $detail): self
    {
        return new self($detail, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public static function tooLarge(string $detail): self
    {
        return new self($detail, Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
    }

    public static function unsupportedEncoding(string $encoding): self
    {
        return new self(\sprintf('Content-Encoding "%s" is not supported; send gzip or nothing.', $encoding), Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
    }
}
