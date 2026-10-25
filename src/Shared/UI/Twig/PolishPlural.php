<?php

declare(strict_types=1);

namespace Watchdog\Shared\UI\Twig;

use Twig\Attribute\AsTwigFilter;

final class PolishPlural
{
    /**
     * {{ 3|plural('linia', 'linie', 'linii') }} gives "linie": one, few (2-4 except 12-14), many.
     */
    #[AsTwigFilter('plural')]
    public function __invoke(int $count, string $one, string $few, string $many): string
    {
        $form = match (true) {
            1 === $count => $one,
            \in_array($count % 10, [2, 3, 4], true) && !\in_array($count % 100, [12, 13, 14], true) => $few,
            default => $many,
        };

        return $form;
    }
}
