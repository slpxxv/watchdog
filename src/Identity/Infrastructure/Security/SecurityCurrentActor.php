<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Port\CurrentActor;
use App\Identity\Domain\User\UserId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

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
