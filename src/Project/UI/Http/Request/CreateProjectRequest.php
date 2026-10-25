<?php

declare(strict_types=1);

namespace Watchdog\Project\UI\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;
use Watchdog\Project\Domain\Project;

final readonly class CreateProjectRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Project::MAX_NAME_LENGTH)]
        public string $name = '',
    ) {
    }
}
