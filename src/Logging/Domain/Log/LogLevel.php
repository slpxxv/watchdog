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

    private const array ALIASES = [
        'trace' => self::Debug,
        'information' => self::Info,
        'informational' => self::Info,
        'warn' => self::Warning,
        'err' => self::Error,
        'fatal' => self::Critical,
        'crit' => self::Critical,
        'emerg' => self::Emergency,
        'panic' => self::Emergency,
    ];

    private const array MONOLOG = [
        100 => self::Debug,
        200 => self::Info,
        250 => self::Notice,
        300 => self::Warning,
        400 => self::Error,
        500 => self::Critical,
        550 => self::Alert,
        600 => self::Emergency,
    ];

    public function label(): string
    {
        return strtolower($this->name);
    }

    /**
     * Reads the level the way shippers send it: PSR-3 names in any case and common aliases
     * ("warn", "fatal", ...), Monolog numbers (100–600) and syslog severities (0 = emergency, 7 = debug).
     *
     * @return ?self null when the value is not recognised
     */
    public static function parse(mixed $value): ?self
    {
        if (\is_string($value)) {
            $name = strtolower(trim($value));
            if (ctype_digit($name)) {
                return self::parse((int) $name);
            }

            return self::ALIASES[$name] ?? array_find(self::cases(), static fn (self $level): bool => $level->label() === $name);
        }

        if (\is_int($value)) {
            return self::MONOLOG[$value] ?? ($value >= 0 && $value <= 7 ? self::from(7 - $value) : null);
        }

        return null;
    }
}
