<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Log;

/**
 * PSR-3 levels, stored as small integers so "warning and above" is a plain comparison.
 */
enum LogLevel: int
{
    case Debug = 0;
    case Info = 1;
    case Notice = 2;
    case Warning = 3;
    case Error = 4;
    case Critical = 5;
    case Alert = 6;
    case Emergency = 7;

    public function label(): string
    {
        return strtolower($this->name);
    }
}
