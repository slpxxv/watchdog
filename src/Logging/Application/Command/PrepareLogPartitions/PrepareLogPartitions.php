<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Command\PrepareLogPartitions;

final readonly class PrepareLogPartitions
{
    // Matches the ingestion window: lines may carry timestamps up to 7 days old.
    public const int DAYS_BACK = 7;
    public const int DAYS_AHEAD = 7;
}
