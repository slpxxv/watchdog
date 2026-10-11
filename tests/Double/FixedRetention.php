<?php

declare(strict_types=1);

namespace Watchdog\Tests\Double;

use Watchdog\Logging\Application\Port\LogRetentionPolicy;

final readonly class FixedRetention implements LogRetentionPolicy
{
    public function __construct(
        private int $days,
    ) {
    }

    public function days(): int
    {
        return $this->days;
    }
}
