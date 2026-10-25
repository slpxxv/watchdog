<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;
use Watchdog\Logging\Application\Query\LogQueryFilters;

/**
 * The filters of the log pages, kept in the URL so a view can be shared or bookmarked.
 */
final readonly class LogsQuery
{
    public const array RANGES = [
        '15m' => ['label' => 'Ostatnie 15 min', 'modifier' => '-15 minutes'],
        '1h' => ['label' => 'Ostatnia godzina', 'modifier' => '-1 hour'],
        '24h' => ['label' => 'Ostatnie 24 h', 'modifier' => '-24 hours'],
        '7d' => ['label' => 'Ostatnie 7 dni', 'modifier' => '-7 days'],
    ];

    public function __construct(
        #[Assert\Length(max: 20)]
        public ?string $level = null,
        #[Assert\Uuid]
        public ?string $source = null,
        #[Assert\Choice(choices: ['15m', '1h', '24h', '7d'])]
        public string $range = '24h',
        #[Assert\Length(max: 500)]
        public ?string $q = null,
        #[Assert\Length(max: 500)]
        public ?string $cursor = null,
        public ?\DateTimeImmutable $from = null,
        #[Assert\PositiveOrZero]
        public int $after = 0,
    ) {
    }

    public function filters(): LogQueryFilters
    {
        return new LogQueryFilters(
            minLevel: '' === $this->level ? null : $this->level,
            sourceIds: null === $this->source || '' === $this->source ? [] : [$this->source],
            text: $this->q,
        );
    }

    /**
     * The current filters as query parameters, for links and the tail URL.
     *
     * @return array<string, string>
     */
    public function parameters(): array
    {
        return array_filter([
            'level' => $this->level,
            'source' => $this->source,
            'q' => $this->q,
        ], static fn (?string $v): bool => null !== $v && '' !== $v);
    }
}
