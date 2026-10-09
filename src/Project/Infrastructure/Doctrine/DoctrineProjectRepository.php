<?php

declare(strict_types=1);

namespace App\Project\Infrastructure\Doctrine;

use App\Project\Domain\Project;
use App\Project\Domain\ProjectId;
use App\Project\Domain\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineProjectRepository implements ProjectRepository
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function nextIdentity(): ProjectId
    {
        return ProjectId::fromString(
            Uuid::v7()->toRfc4122(),
        );
    }

    public function ofId(ProjectId $id): ?Project
    {
        return $this->em->find(Project::class, $id->value);
    }

    public function save(Project $project): void
    {
        $this->em->persist($project);
        $this->em->flush();
    }

    public function all(): array
    {
        /** @var list<Project> */
        return $this->em->getRepository(Project::class)->findBy([], ['createdAt' => 'ASC']);
    }
}
