<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine;

use App\Identity\Domain\User\Email;
use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserId;
use App\Identity\Domain\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineUserRepository implements UserRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function nextIdentity(): UserId
    {
        return UserId::fromString(Uuid::v7()->toRfc4122());
    }

    public function save(User $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }

    public function ofId(UserId $id): ?User
    {
        return $this->em->find(User::class, $id->value);
    }

    public function ofEmail(Email $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email->value]);
    }

    public function page(int $offset, int $limit): array
    {
        /** @var list<User> */
        return $this->em->getRepository(User::class)->findBy([], ['email' => 'ASC'], $limit, $offset);
    }

    public function count(): int
    {
        return $this->em->getRepository(User::class)->count();
    }

    public function countSuperAdmins(): int
    {
        return (int) $this->em->createQuery(
            'SELECT COUNT(DISTINCT u.id) FROM '.User::class.' u JOIN u.roles r WHERE r.superAdmin = true',
        )->getSingleScalarResult();
    }
}
