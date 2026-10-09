<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Application;

use App\Project\Application\Command\CreateProject\CreateProject;
use App\Project\Application\Command\CreateProject\CreateProjectHandler;
use App\Project\Application\Command\RenameProject\RenameProject;
use App\Project\Application\Command\RenameProject\RenameProjectHandler;
use App\Project\Application\Dto\ProjectDto;
use App\Project\Application\Query\GetProject\GetProject;
use App\Project\Application\Query\GetProject\GetProjectHandler;
use App\Project\Application\Query\ListProjects\ListProjects;
use App\Project\Application\Query\ListProjects\ListProjectsHandler;
use App\Project\Domain\Exception\ProjectNotFound;
use App\Project\Domain\ProjectId;
use App\Tests\Double\InMemoryProjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ProjectHandlersTest extends TestCase
{
    private const string UNKNOWN_ID = '00000000-0000-7000-a000-999999999999';

    private InMemoryProjectRepository $projects;
    private CreateProjectHandler $createProject;

    protected function setUp(): void
    {
        $this->projects = new InMemoryProjectRepository();
        $this->createProject = new CreateProjectHandler($this->projects, new MockClock('2026-01-01 12:00:00 UTC'));
    }

    private function create(string $name): ProjectId
    {
        return ($this->createProject)(new CreateProject($name));
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

        (new RenameProjectHandler($this->projects))(new RenameProject($id->value, 'Renamed'));

        self::assertSame('Renamed', $this->projects->ofId($id)?->name());
    }

    public function testRenameUnknownProjectThrows(): void
    {
        $this->expectException(ProjectNotFound::class);
        (new RenameProjectHandler($this->projects))(new RenameProject(self::UNKNOWN_ID, 'Renamed'));
    }

    public function testGetReturnsDto(): void
    {
        $id = $this->create('Watchdog');

        $dto = (new GetProjectHandler($this->projects))(new GetProject($id->value));

        self::assertSame($id->value, $dto->id);
        self::assertSame('Watchdog', $dto->name);
        self::assertSame('2026-01-01T12:00:00+00:00', $dto->createdAt);
    }

    public function testGetUnknownProjectThrows(): void
    {
        try {
            (new GetProjectHandler($this->projects))(new GetProject(self::UNKNOWN_ID));
            self::fail('Expected ProjectNotFound.');
        } catch (ProjectNotFound $e) {
            self::assertSame('project.not_found', $e->messageKey);
            self::assertSame(['%id%' => self::UNKNOWN_ID], $e->messageParameters);
        }
    }

    public function testListIsEmptyWithoutProjects(): void
    {
        self::assertSame([], (new ListProjectsHandler($this->projects))(new ListProjects()));
    }

    public function testListReturnsAllProjects(): void
    {
        $this->create('Alpha');
        $this->create('Beta');

        $names = array_map(static fn (ProjectDto $p): string => $p->name, (new ListProjectsHandler($this->projects))(new ListProjects()));
        self::assertSame(['Alpha', 'Beta'], $names);
    }
}
