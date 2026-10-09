<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Project\Domain;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Watchdog\Project\Domain\Exception\InvalidProjectName;
use Watchdog\Project\Domain\Project;
use Watchdog\Project\Domain\ProjectId;

final class ProjectTest extends TestCase
{
    private const string ID = '00000000-0000-7000-a000-000000000001';

    private function project(string $name = 'Watchdog'): Project
    {
        return Project::create(ProjectId::fromString(self::ID), $name, new \DateTimeImmutable('2026-01-01'));
    }

    public function testCreateTrimsName(): void
    {
        $project = $this->project('  Watchdog  ');

        self::assertSame(self::ID, $project->id()->value);
        self::assertSame('Watchdog', $project->name());
        self::assertEquals(new \DateTimeImmutable('2026-01-01'), $project->createdAt());
    }

    public function testRenameTrims(): void
    {
        $project = $this->project();
        $project->rename('  Renamed  ');

        self::assertSame('Renamed', $project->name());
    }

    public function testNameLimitCountsCharactersNotBytes(): void
    {
        $name = str_repeat('ż', Project::MAX_NAME_LENGTH);

        self::assertSame($name, $this->project($name)->name());
    }

    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith(['x', Project::MAX_NAME_LENGTH + 1])]
    public function testCreateRejectsBlankOrTooLong(string $char, int $times = 1): void
    {
        $this->expectException(InvalidProjectName::class);
        $this->project(str_repeat($char, $times));
    }

    public function testFailedRenameKeepsOldName(): void
    {
        $project = $this->project();

        try {
            $project->rename('   ');
            self::fail('Expected InvalidProjectName.');
        } catch (InvalidProjectName $e) {
            self::assertSame('project.name.invalid', $e->messageKey);
            self::assertSame(['%max%' => Project::MAX_NAME_LENGTH], $e->messageParameters);
        }

        self::assertSame('Watchdog', $project->name());
    }
}
