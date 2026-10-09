<?php

declare(strict_types=1);

namespace App\Project\Application\Query\GetProject;

final readonly class GetProject
{
    public function __construct(
        public string $id,
    ) {
    }
}
