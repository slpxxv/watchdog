<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Domain\Role\Role;
use App\Identity\Domain\User\Email;
use App\Identity\Domain\User\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateUserCommandTest extends KernelTestCase
{
    private const string PASSWORD = 'correct horse battery staple';

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->tester = new CommandTester((new Application(self::bootKernel()))->find('app:user:create'));
    }

    /**
     * @return list<string>
     */
    private function roleCodes(string $email): array
    {
        $user = self::getContainer()->get(UserRepository::class)->ofEmail(Email::fromString($email));
        self::assertNotNull($user);

        $codes = array_map(static fn (Role $r): string => $r->code(), $user->roles());
        sort($codes);

        return $codes;
    }

    public function testCreatesUser(): void
    {
        $this->tester->setInputs([self::PASSWORD]);

        self::assertSame(Command::SUCCESS, $this->tester->execute(['email' => 'jane@example.com']));
        self::assertStringContainsString('User jane@example.com created', $this->tester->getDisplay());
        self::assertSame([Role::USER], $this->roleCodes('jane@example.com'));
    }

    public function testAdminOptionGrantsSuperAdmin(): void
    {
        $this->tester->setInputs([self::PASSWORD]);

        self::assertSame(Command::SUCCESS, $this->tester->execute(['email' => 'root@example.com', '--admin' => true]));
        self::assertSame([Role::SUPER_ADMIN, Role::USER], $this->roleCodes('root@example.com'));
    }

    public function testDomainErrorFailsWithMessage(): void
    {
        $this->tester->setInputs(['short']);

        self::assertSame(Command::FAILURE, $this->tester->execute(['email' => 'jane@example.com']));
        self::assertStringContainsString('Password must be at least', $this->tester->getDisplay());
        self::assertNull(self::getContainer()->get(UserRepository::class)->ofEmail(Email::fromString('jane@example.com')));
    }

    public function testDuplicateEmailFails(): void
    {
        $this->tester->setInputs([self::PASSWORD]);
        $this->tester->execute(['email' => 'jane@example.com']);

        $this->tester->setInputs([self::PASSWORD]);
        self::assertSame(Command::FAILURE, $this->tester->execute(['email' => 'jane@example.com']));
        self::assertStringContainsString('already exists', $this->tester->getDisplay());
    }
}
