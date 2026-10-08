<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

use App\Identity\Domain\User\Exception\InvalidEmail;

final readonly class Email
{
    public const int MAX_LENGTH = 180;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = mb_strtolower(trim($value));

        if (mb_strlen($normalized) > self::MAX_LENGTH || false === filter_var($normalized, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidEmail::for($value);
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
