<?php

declare(strict_types=1);

namespace Watchdog\Identity\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Identity\Domain\Role\RoleId;
use Watchdog\Identity\Domain\Role\RoleRepository;

final readonly class DoctrineRoleRepository implements RoleRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function nextIdentity(): RoleId
    {
        return RoleId::fromString(Uuid::v7()->toRfc4122());
    }

    public function save(Role $role): void
    {
        $this->em->persist($role);
        $this->em->flush();
    }

    public function remove(Role $role): void
    {
        $this->em->remove($role);
        $this->em->flush();
    }

    public function ofId(RoleId $id): ?Role
    {
        return $this->em->find(Role::class, $id->value);
    }

    public function ofCode(string $code): ?Role
    {
        return $this->em->getRepository(Role::class)->findOneBy(['code' => $code]);
    }

    public function all(): array
    {
        /** @var list<Role> */
        return $this->em->getRepository(Role::class)->findBy([], ['superAdmin' => 'DESC', 'system' => 'DESC', 'name' => 'ASC']);
    }
}
