<?php

declare(strict_types=1);

namespace Watchdog\Shared\UI\Http;

use Watchdog\Shared\Domain\Error\DomainError;
use Watchdog\Shared\Domain\Error\Forbidden;
use Watchdog\Shared\Domain\Error\NotFound;

/**
 * For AbstractController actions that answer a failed form with a flash message: the page translates
 * the key (translations/domain.pl.yaml), so the wording is not decided here. "Not found" and
 * "forbidden" are not form mistakes: they are rethrown for the error pages.
 */
trait FlashesDomainErrors
{
    private function flashDomainError(DomainError $error): void
    {
        if ($error instanceof NotFound || $error instanceof Forbidden) {
            throw $error;
        }

        $this->addFlash('error', ['key' => $error->messageKey, 'parameters' => $error->messageParameters]);
    }
}
