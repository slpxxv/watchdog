<?php

declare(strict_types=1);

namespace Watchdog\Identity\Infrastructure\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Watchdog\Identity\Application\Port\CurrentActor;
use Watchdog\Identity\Domain\User\UserId;

final readonly class SecurityCurrentActor implements CurrentActor
{
    public function __construct(private Security $security)
    {
    }

    public function id(): UserId
    {
        $user = $this->security->getUser();
        if (!$user instanceof SecurityUser) {
            throw new AccessDeniedException();
        }

        return UserId::fromString($user->id);
    }
}
