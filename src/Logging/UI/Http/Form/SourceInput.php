<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http\Form;

use Symfony\Component\Validator\Constraints as Assert;
use Watchdog\Logging\Domain\Source\Platform;
use Watchdog\Logging\Domain\Source\Source;

final class SourceInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: Source::MAX_NAME_LENGTH)]
    public string $name = '';

    public Platform $platform = Platform::Http;
}
