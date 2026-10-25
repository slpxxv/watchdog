<?php

declare(strict_types=1);

namespace Watchdog\Identity\UI\Console;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Watchdog\Identity\Application\Command\CreateUser\CreateUser;
use Watchdog\Identity\Application\Command\CreateUser\CreateUserHandler;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Shared\Domain\Error\DomainError;

#[AsCommand(name: 'app:user:create', description: 'Creates a user account')]
final readonly class CreateUserCommand
{
    public function __construct(private CreateUserHandler $handler)
    {
    }

    /**
     * @param list<string> $role
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('User email')] string $email,
        #[Option('Grant the super admin role')] bool $admin = false,
        #[Option('Role code to assign (repeatable)')] array $role = [],
    ): int {
        $password = $io->askHidden('Password (min. '.CreateUserHandler::MIN_PASSWORD_LENGTH.' chars)');
        if (!\is_string($password)) {
            $io->error('Password is required.');

            return Command::FAILURE;
        }

        $roles = [Role::USER, ...$role, ...($admin ? [Role::SUPER_ADMIN] : [])];

        try {
            $id = ($this->handler)(new CreateUser(
                email: $email,
                plainPassword: $password,
                roleCodes: $roles,
            ));
        } catch (DomainError $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('User %s created (id: %s).', $email, $id->value));

        return Command::SUCCESS;
    }
}
