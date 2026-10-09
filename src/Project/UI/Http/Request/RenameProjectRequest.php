<?php

declare(strict_types=1);

namespace App\Project\UI\Http\Request;

use App\Project\Domain\Project;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RenameProjectRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Project::MAX_NAME_LENGTH)]
        public string $name = '',
    ) {
    }
}
