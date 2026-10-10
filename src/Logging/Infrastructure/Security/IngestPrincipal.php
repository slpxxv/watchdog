<?php

declare(strict_types=1);

namespace Watchdog\Logging\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Who is calling the ingest endpoint: a log source, identified by its id. Never stored in a session.
 */
final readonly class IngestPrincipal implements UserInterface
{
    public const string ROLE = 'ROLE_INGEST';

    /** @var non-empty-string */
    private string $sourceId;

    public function __construct(string $sourceId)
    {
        if ('' === $sourceId) {
            throw new \InvalidArgumentException('A source id cannot be empty.');
        }

        $this->sourceId = $sourceId;
    }

    public function getRoles(): array
    {
        return [self::ROLE];
    }

    public function getUserIdentifier(): string
    {
        return $this->sourceId;
    }
}
