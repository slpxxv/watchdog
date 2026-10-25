<?php

declare(strict_types=1);

namespace Watchdog\Logging\Domain\Source;

/**
 * How a source sends its logs, chosen when it is connected; it picks the setup shown for it.
 */
enum Platform: string
{
    case Http = 'http';
    case Vector = 'vector';
    case FluentBit = 'fluent-bit';
    case Monolog = 'monolog';
}
