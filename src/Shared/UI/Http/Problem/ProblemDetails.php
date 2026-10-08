<?php

declare(strict_types=1);

namespace App\Shared\UI\Http\Problem;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * RFC 9457 error body. Extension members sit next to the standard ones and can never replace them.
 */
final readonly class ProblemDetails
{
    public const string CONTENT_TYPE = 'application/problem+json';
    public const string DEFAULT_TYPE = 'about:blank';

    /**
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        public int $status,
        public string $title,
        public ?string $detail = null,
        public string $type = self::DEFAULT_TYPE,
        public array $extensions = [],
    ) {
        if ($status < 400 || $status > 599) {
            throw new \InvalidArgumentException(\sprintf('Problem details need an error status, got %d.', $status));
        }
    }

    /**
     * @param array<string, mixed> $extensions
     */
    public static function forStatus(int $status, ?string $detail = null, array $extensions = []): self
    {
        return new self($status, Response::$statusTexts[$status] ?? 'Error', $detail, extensions: $extensions);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
        ], static fn (mixed $v): bool => null !== $v) + $this->extensions;
    }

    public function toResponse(): JsonResponse
    {
        return new JsonResponse($this->toArray(), $this->status, ['Content-Type' => self::CONTENT_TYPE]);
    }
}
