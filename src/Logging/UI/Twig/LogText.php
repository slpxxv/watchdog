<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Twig;

use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * Log text comes from clients: it is shown as plain text, minus terminal control codes.
 */
final class LogText
{
    // ANSI escape sequences (colours, cursor moves) and other control characters, keeping tab and newline.
    private const string CONTROL = '/\x1b\[[0-?]*[ -\/]*[@-~]|\x1b[@-Z\\\\-_]|[\x00-\x08\x0b-\x1f\x7f]/';

    #[AsTwigFilter('log_text')]
    public function plain(string $text): string
    {
        return preg_replace(self::CONTROL, '', $text) ?? '';
    }

    /**
     * Top-level context fields as short key=value pairs for the one-line view.
     *
     * @param array<array-key, mixed> $context
     *
     * @return list<array{string, string}>
     */
    #[AsTwigFunction('log_fields')]
    public function fields(array $context, int $max = 4): array
    {
        $pairs = [];
        foreach (\array_slice($context, 0, $max, true) as $key => $value) {
            $text = \is_string($value) ? $this->plain($value) : json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR);
            $text = \is_string($text) ? $text : '';
            $pairs[] = [(string) $key, mb_strlen($text) > 60 ? mb_substr($text, 0, 59).'…' : $text];
        }

        return $pairs;
    }

    /**
     * @param array<array-key, mixed> $context
     */
    #[AsTwigFunction('log_json')]
    public function json(array $context): string
    {
        return json_encode($context ?: new \stdClass(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
    }
}
