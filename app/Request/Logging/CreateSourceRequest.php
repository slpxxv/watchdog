<?php

declare(strict_types=1);

namespace Watchdog\App\Request\Logging;

use Symfony\Component\Validator\Constraints as Assert;
use Watchdog\Logging\Domain\Source\Source;

final readonly class CreateSourceRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: Source::MAX_NAME_LENGTH)]
        public string $name = '',
    ) {
    }
}
