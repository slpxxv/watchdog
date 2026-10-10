<?php

declare(strict_types=1);

namespace Watchdog\Tests\Double;

use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\Source;
use Watchdog\Logging\Domain\Source\SourceId;
use Watchdog\Logging\Domain\Source\SourceRepository;

final class InMemorySourceRepository implements SourceRepository
{
    /** @var array<string, Source> */
    private array $sources = [];
    private int $sequence = 0;

    public function nextIdentity(): SourceId
    {
        return SourceId::fromString(\sprintf('00000000-0000-7000-c000-%012d', ++$this->sequence));
    }

    public function save(Source $source): void
    {
        $this->sources[$source->id()->value] = $source;
    }

    public function ofId(SourceId $id): ?Source
    {
        return $this->sources[$id->value] ?? null;
    }

    public function ofTokenHash(string $tokenHash): ?Source
    {
        return array_find($this->sources, static fn (Source $s): bool => (fn (): string => $this->tokenHash)->call($s) === $tokenHash);
    }

    public function ofProject(ProjectId $projectId): array
    {
        return array_values(array_filter($this->sources, static fn (Source $s): bool => $s->projectId()->equals($projectId)));
    }
}
