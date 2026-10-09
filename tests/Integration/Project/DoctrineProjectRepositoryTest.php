<?php

declare(strict_types=1);

namespace Watchdog\Tests\Integration\Project;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Watchdog\Project\Domain\Project;
use Watchdog\Project\Domain\ProjectRepository;

final class DoctrineProjectRepositoryTest extends KernelTestCase
{
    private ProjectRepository $projects;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->projects = self::getContainer()->get(ProjectRepository::class);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    private function project(string $name, string $createdAt = '2026-01-01 12:00:00'): Project
    {
        $project = Project::create($this->projects->nextIdentity(), $name, new \DateTimeImmutable($createdAt));
        $this->projects->save($project);

        return $project;
    }

    public function testSavedProjectIsHydratedFromDatabase(): void
    {
        $id = $this->project('Watchdog')->id();
        $this->em->clear();

        $project = $this->projects->ofId($id);

        self::assertNotNull($project);
        self::assertTrue($id->equals($project->id()));
        self::assertSame('Watchdog', $project->name());
        self::assertSame('2026-01-01 12:00:00', $project->createdAt()->format('Y-m-d H:i:s'));
    }

    public function testRenameIsPersisted(): void
    {
        $project = $this->project('Watchdog');
        $project->rename('Renamed');
        $this->projects->save($project);
        $this->em->clear();

        self::assertSame('Renamed', $this->projects->ofId($project->id())?->name());
    }

    public function testRemoveDeletesRow(): void
    {
        $project = $this->project('Watchdog');
        $this->projects->remove($project);
        $this->em->clear();

        self::assertNull($this->projects->ofId($project->id()));
    }

    public function testUnknownIdReturnsNull(): void
    {
        self::assertNull($this->projects->ofId($this->projects->nextIdentity()));
    }

    public function testNextIdentityIsUnique(): void
    {
        self::assertFalse($this->projects->nextIdentity()->equals($this->projects->nextIdentity()));
    }

    public function testAllIsOrderedByCreationDate(): void
    {
        $this->project('Newer', '2026-03-01');
        $this->project('Older', '2026-01-01');
        $this->em->clear();

        $names = array_map(static fn (Project $p): string => $p->name(), $this->projects->all());

        self::assertSame(['Older', 'Newer'], $names);
    }
}
