<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Source;

use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\Exception\InvalidSourceName;
use Watchdog\Logging\Domain\Source\Exception\SourceRevoked;

/**
 * Something that sends logs to a project (an app, a server, a shipper), identified by its token.
 */
class Source
{
    public const int MAX_NAME_LENGTH = 100;

    private string $id;

    private string $projectId;

    private string $name;

    private string $tokenHash;

    private string $tokenPrefix;

    private ?\DateTimeImmutable $revokedAt = null;

    private function __construct(
        SourceId $id,
        ProjectId $projectId,
        string $name,
        SourceToken $token,
        private \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id->value;
        $this->projectId = $projectId->value;
        $this->rename($name);
        $this->useToken($token);
    }

    public static function create(SourceId $id, ProjectId $projectId, string $name, SourceToken $token, \DateTimeImmutable $now): self
    {
        return new self(
            id: $id,
            projectId: $projectId,
            name: $name,
            token: $token,
            createdAt: $now,
        );
    }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw InvalidSourceName::for($name);
        }

        $this->name = $name;
    }

    /**
     * The previous token stops working immediately.
     */
    public function rotateToken(SourceToken $token): void
    {
        if ($this->isRevoked()) {
            throw SourceRevoked::withId($this->id());
        }

        $this->useToken($token);
    }

    /**
     * Permanent: a revoked source cannot get a new token. Revoking twice keeps the first date.
     */
    public function revoke(\DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function id(): SourceId
    {
        return SourceId::fromString($this->id);
    }

    public function projectId(): ProjectId
    {
        return ProjectId::fromString($this->projectId);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function tokenPrefix(): string
    {
        return $this->tokenPrefix;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function revokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    private function useToken(SourceToken $token): void
    {
        $this->tokenHash = $token->hash();
        $this->tokenPrefix = $token->prefix();
    }
}
