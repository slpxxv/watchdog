<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Dto;

use Watchdog\Logging\Domain\Source\Source;

final readonly class SourceDto
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $name,
        public string $tokenPrefix,
        public string $createdAt,
        public ?string $revokedAt,
        public string $platform,
        public ?SourceActivityDto $activity = null,
    ) {
    }

    /**
     * $activity only where it was looked up (the source list); null elsewhere.
     */
    public static function fromSource(Source $source, ?SourceActivityDto $activity = null): self
    {
        return new self(
            id: $source->id()->value,
            projectId: $source->projectId()->value,
            name: $source->name(),
            tokenPrefix: $source->tokenPrefix(),
            createdAt: $source->createdAt()->format(\DATE_ATOM),
            revokedAt: $source->revokedAt()?->format(\DATE_ATOM),
            platform: $source->platform()->value,
            activity: $activity,
        );
    }
}
