<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Shared\UI\Http\Problem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Watchdog\Identity\Application\Exception\WeakPassword;
use Watchdog\Identity\Domain\Role\Exception\CannotDeleteSystemRole;
use Watchdog\Identity\Domain\Role\Exception\RoleAlreadyExists;
use Watchdog\Identity\Domain\Role\Exception\RoleNotFound;
use Watchdog\Identity\Domain\User\Email;
use Watchdog\Identity\Domain\User\Exception\PrivilegeEscalation;
use Watchdog\Identity\Domain\User\Exception\UserAlreadyExists;
use Watchdog\Identity\Domain\User\Exception\UserNotFound;
use Watchdog\Identity\Domain\User\UserId;
use Watchdog\Shared\Domain\Error\DomainError;
use Watchdog\Shared\UI\Http\Problem\DomainErrorMapper;

final class DomainErrorMapperTest extends TestCase
{
    /**
     * @return iterable<string, array{DomainError, int}>
     */
    public static function errors(): iterable
    {
        yield 'role not found' => [RoleNotFound::withCode('editor'), 404];
        yield 'user not found' => [UserNotFound::withId(UserId::fromString('01a11c9a-0c58-715b-a7c3-cadec5d3f36a')), 404];
        yield 'role conflict' => [RoleAlreadyExists::withCode('editor'), 409];
        yield 'user conflict' => [UserAlreadyExists::withEmail(Email::fromString('a@example.com')), 409];
        yield 'privilege escalation' => [PrivilegeEscalation::superAdminOnly(), 403];
        yield 'business rule' => [CannotDeleteSystemRole::code('user'), 422];
        yield 'application rule' => [WeakPassword::tooShort(12), 422];
    }

    #[DataProvider('errors')]
    public function testStatusFollowsMarkerInterface(DomainError $error, int $status): void
    {
        self::assertSame($status, (new DomainErrorMapper())->statusOf($error));
    }

    public function testItCarriesTranslationKeyAndParameters(): void
    {
        $error = RoleAlreadyExists::withCode('editor');

        $problem = (new DomainErrorMapper())->map($error);

        self::assertSame(409, $problem->status);
        self::assertSame('Role "editor" already exists.', $problem->detail);
        self::assertSame([
            DomainErrorMapper::MESSAGE_KEY => 'identity.role.already_exists',
            DomainErrorMapper::MESSAGE_PARAMETERS => ['%code%' => 'editor'],
        ], $problem->extensions);
    }
}
