<?php

declare(strict_types=1);

namespace App\Shared\Domain;

abstract readonly class Uuid implements \Stringable
{
    final private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): static
    {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            throw new \InvalidArgumentException(\sprintf('Invalid %s.', static::class));
        }

        return new static(strtolower($value));
    }

    public function equals(self $other): bool
    {
        return $other instanceof static && $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
