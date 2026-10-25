<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Project\Application;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Watchdog\Project\Application\Command\CreateProject;
use Watchdog\Project\Application\Command\DeleteProject;
use Watchdog\Project\Application\Command\RenameProject;
use Watchdog\Project\Application\Dto\ProjectDto;
use Watchdog\Project\Application\Event\ProjectDeleted;
use Watchdog\Project\Application\Query\GetProject;
use Watchdog\Project\Application\Query\ListProjects;
use Watchdog\Project\Application\Query\ProjectExists;
use Watchdog\Project\Domain\Exception\ProjectNotFound;
use Watchdog\Project\Domain\ProjectId;
use Watchdog\Tests\Double\InMemoryProjectRepository;
use Watchdog\Tests\Double\RecordingEventDispatcher;

final class ProjectUseCasesTest extends TestCase
{
    private const string UNKNOWN_ID = '00000000-0000-7000-a000-999999999999';

    private InMemoryProjectRepository $projects;
    private CreateProject $createProject;

    protected function setUp(): void
    {
        $this->projects = new InMemoryProjectRepository();
        $this->createProject = new CreateProject($this->projects, new MockClock('2026-01-01 12:00:00 UTC'));
    }

    private function create(string $name): ProjectId
    {
        return ($this->createProject)($name);
    }

    public function testCreatePersistsProject(): void
    {
        $id = $this->create('Watchdog');

        $project = $this->projects->ofId($id);
        self::assertNotNull($project);
        self::assertSame('Watchdog', $project->name());
        self::assertSame('2026-01-01T12:00:00+00:00', $project->createdAt()->format(\DATE_ATOM));
    }

    public function testRenameChangesName(): void
    {
        $id = $this->create('Watchdog');

        (new RenameProject($this->projects))($id->value, 'Renamed');

        self::assertSame('Renamed', $this->projects->ofId($id)?->name());
    }

    public function testRenameUnknownProjectThrows(): void
    {
        $this->expectException(ProjectNotFound::class);
        (new RenameProject($this->projects))(self::UNKNOWN_ID, 'Renamed');
    }

    public function testDeleteRemovesProject(): void
    {
        $kept = $this->create('Kept');
        $deleted = $this->create('Deleted');

        $events = new RecordingEventDispatcher();

        (new DeleteProject($this->projects, $events))($deleted->value);

        self::assertNull($this->projects->ofId($deleted));
        self::assertNotNull($this->projects->ofId($kept));
        self::assertEquals([new ProjectDeleted($deleted->value)], $events->events);
    }

    public function testDeleteUnknownProjectThrowsWithoutPublishing(): void
    {
        $events = new RecordingEventDispatcher();

        try {
            (new DeleteProject($this->projects, $events))(self::UNKNOWN_ID);
            self::fail('Expected ProjectNotFound.');
        } catch (ProjectNotFound) {
        }

        self::assertSame([], $events->events);
    }

    public function testProjectExists(): void
    {
        $id = $this->create('Watchdog');
        $exists = new ProjectExists($this->projects);

        self::assertTrue($exists($id->value));
        self::assertFalse($exists(self::UNKNOWN_ID));
    }

    public function testGetReturnsDto(): void
    {
        $id = $this->create('Watchdog');

        $dto = (new GetProject($this->projects))($id->value);

        self::assertSame($id->value, $dto->id);
        self::assertSame('Watchdog', $dto->name);
        self::assertSame('2026-01-01T12:00:00+00:00', $dto->createdAt);
    }

    public function testGetUnknownProjectThrows(): void
    {
        try {
            (new GetProject($this->projects))(self::UNKNOWN_ID);
            self::fail('Expected ProjectNotFound.');
        } catch (ProjectNotFound $e) {
            self::assertSame('project.not_found', $e->messageKey);
            self::assertSame(['%id%' => self::UNKNOWN_ID], $e->messageParameters);
        }
    }

    public function testListIsEmptyWithoutProjects(): void
    {
        self::assertSame([], (new ListProjects($this->projects))());
    }

    public function testListReturnsAllProjects(): void
    {
        $this->create('Alpha');
        $this->create('Beta');

        $names = array_map(static fn (ProjectDto $p): string => $p->name, (new ListProjects($this->projects))());
        self::assertSame(['Alpha', 'Beta'], $names);
    }
}
