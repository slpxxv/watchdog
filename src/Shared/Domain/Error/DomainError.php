<?php

declare(strict_types=1);

namespace Watchdog\Shared\Domain\Error;

/**
 * messageKey + messageParameters are for the client to translate; getMessage() is for logs/CLI.
 */
abstract class DomainError extends \DomainException
{
    /**
     * @param array<string, string|int> $messageParameters
     */
    final protected function __construct(
        string $message,
        public readonly string $messageKey,
        public readonly array $messageParameters = [],
    ) {
        parent::__construct($message);
    }
}
