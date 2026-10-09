<?php

declare(strict_types=1);

namespace App\Project\Domain;

use App\Project\Domain\Exception\InvalidProjectName;

class Project
{
    public const int MAX_NAME_LENGTH = 100;

    private string $id;

    private string $name;

    private function __construct(
        ProjectId $id,
        string $name,
        private \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id->value;
        $this->rename($name);
    }

    public static function create(ProjectId $id, string $name, \DateTimeImmutable $createdAt): self
    {
        return new self($id, $name, $createdAt);
    }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw InvalidProjectName::for($name);
        }

        $this->name = $name;
    }

    public function id(): ProjectId
    {
        return ProjectId::fromString($this->id);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
