<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command\ApplyLogRetention;

final readonly class ApplyLogRetention
{
    /**
     * Ingestion accepts timestamps up to 7 days old (LineNormalizer::WINDOW_PAST). Dropping a day that
     * can still receive lines would send them to the default partition, after which that day's
     * partition could never be recreated.
     */
    public const int MIN_DAYS = 8;
}
