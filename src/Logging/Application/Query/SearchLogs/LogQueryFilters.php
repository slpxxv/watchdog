<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Query\SearchLogs;

use Watchdog\Logging\Domain\Log\Exception\InvalidLogQuery;
use Watchdog\Logging\Domain\Log\LogLevel;
use Watchdog\Logging\Domain\Source\SourceId;

/**
 * Filters shared by search and tail, as the caller sent them, turned into domain values.
 */
final readonly class LogQueryFilters
{
    /**
     * @param list<string> $sourceIds
     */
    public function __construct(
        public ?string $minLevel = null,
        public array $sourceIds = [],
        public ?string $text = null,
        public ?string $context = null,
    ) {
    }

    public function level(): ?LogLevel
    {
        if (null === $this->minLevel || '' === $this->minLevel) {
            return null;
        }

        return array_find(LogLevel::cases(), fn (LogLevel $l): bool => $l->label() === $this->minLevel)
            ?? throw InvalidLogQuery::because(\sprintf('Unknown level "%s".', $this->minLevel));
    }

    /**
     * @return list<SourceId>
     */
    public function sources(): array
    {
        try {
            return array_map(SourceId::fromString(...), $this->sourceIds);
        } catch (\InvalidArgumentException) {
            throw InvalidLogQuery::because('Every source must be a UUID.');
        }
    }

    public function text(): ?string
    {
        $text = null === $this->text ? '' : trim($this->text);

        return '' === $text ? null : $text;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function contextObject(): ?array
    {
        if (null === $this->context || '' === trim($this->context)) {
            return null;
        }

        try {
            $decoded = json_decode($this->context, true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw InvalidLogQuery::because('The context filter must be a JSON object.');
        }
        if (!\is_array($decoded) || ([] !== $decoded && array_is_list($decoded))) {
            throw InvalidLogQuery::because('The context filter must be a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
