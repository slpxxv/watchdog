<?php

declare(strict_types=1);

namespace Watchdog\Project\UI\Http\Form;

use Symfony\Component\Validator\Constraints as Assert;
use Watchdog\Project\Domain\Project;

final class ProjectInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: Project::MAX_NAME_LENGTH)]
    public string $name = '';
}
