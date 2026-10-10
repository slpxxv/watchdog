<?php

declare(strict_types=1);

namespace Watchdog\Logging\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\Source;
use Watchdog\Logging\Domain\Source\SourceId;
use Watchdog\Logging\Domain\Source\SourceRepository;

final readonly class DoctrineSourceRepository implements SourceRepository
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function nextIdentity(): SourceId
    {
        return SourceId::fromString(Uuid::v7()->toRfc4122());
    }

    public function save(Source $source): void
    {
        $this->em->persist($source);
        $this->em->flush();
    }

    public function ofId(SourceId $id): ?Source
    {
        return $this->em->find(Source::class, $id->value);
    }

    public function ofTokenHash(string $tokenHash): ?Source
    {
        return $this->em->getRepository(Source::class)->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function ofProject(ProjectId $projectId): array
    {
        /** @var list<Source> */
        return $this->em->getRepository(Source::class)->findBy(['projectId' => $projectId->value], ['createdAt' => 'ASC']);
    }
}
