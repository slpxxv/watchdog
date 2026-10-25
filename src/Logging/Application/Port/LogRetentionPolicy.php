<?php

declare(strict_types=1);

namespace Watchdog\Logging\Application\Port;

interface LogRetentionPolicy
{
    /**
     * How many days of logs to keep, counting today.
     */
    public function days(): int;
}
