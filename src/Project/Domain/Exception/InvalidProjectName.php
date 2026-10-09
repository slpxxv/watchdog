<?php

declare(strict_types=1);

namespace App\Project\Domain\Exception;

use App\Project\Domain\Project;
use App\Shared\Domain\Error\DomainError;

final class InvalidProjectName extends DomainError
{
    public static function for(string $name): self
    {
        return new self(
            message: \sprintf('Project name "%s" must be 1-%d characters.', $name, Project::MAX_NAME_LENGTH),
            messageKey: 'project.name.invalid',
            messageParameters: ['%max%' => Project::MAX_NAME_LENGTH],
        );
    }
}
