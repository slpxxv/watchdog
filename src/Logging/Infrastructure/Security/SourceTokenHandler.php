<?php

declare(strict_types=1);

namespace Watchdog\Logging\Infrastructure\Security;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Watchdog\Logging\Domain\Source\SourceRepository;
use Watchdog\Logging\Domain\Source\SourceToken;

/**
 * Resolves "Authorization: Bearer wd_…" to its source by hash. Unknown and revoked tokens get the
 * same answer, so a caller cannot tell a revoked token from a made-up one.
 */
final readonly class SourceTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private SourceRepository $sources,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $source = $this->sources->ofTokenHash(SourceToken::hashOf($accessToken));
        if (null === $source || $source->isRevoked()) {
            throw new BadCredentialsException('Invalid source token.');
        }

        $id = $source->id()->value;

        return new UserBadge($id, static fn (): IngestPrincipal => new IngestPrincipal($id));
    }
}
