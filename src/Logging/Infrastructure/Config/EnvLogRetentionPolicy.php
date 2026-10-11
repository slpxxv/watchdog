<?php

declare(strict_types=1);

namespace Watchdog\Logging\Infrastructure\Config;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Watchdog\Logging\Application\Port\LogRetentionPolicy;

final readonly class EnvLogRetentionPolicy implements LogRetentionPolicy
{
    public function __construct(
        #[Autowire('%env(int:LOG_RETENTION_DAYS)%')]
        private int $days,
    ) {
    }

    public function days(): int
    {
        return $this->days;
    }
}
