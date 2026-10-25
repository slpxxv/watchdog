<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;
use Watchdog\Logging\Domain\Source\Platform;

/**
 * Setup text for a new source, kept in step with README.md "Sending logs".
 */
final readonly class Snippets
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    /**
     * What the platform picker and the setup tabs show: name, a one-line hint and the short icon label.
     *
     * @return array<string, array{name: string, hint: string, mark: string}>
     */
    #[AsTwigFunction('platforms')]
    public function platforms(): array
    {
        return [
            Platform::Http->value => ['name' => 'HTTP', 'hint' => 'curl, dowolny klient', 'mark' => 'http'],
            Platform::Vector->value => ['name' => 'Vector', 'hint' => 'pliki, Docker, Kubernetes', 'mark' => 'vec'],
            Platform::FluentBit->value => ['name' => 'Fluent Bit', 'hint' => 'lekki agent na serwery', 'mark' => 'fb'],
            Platform::Monolog->value => ['name' => 'PHP / Monolog', 'hint' => 'Symfony, Laravel', 'mark' => 'php'],
        ];
    }

    #[AsTwigFunction('setup_snippet')]
    public function snippet(string $platform, #[\SensitiveParameter] string $token): string
    {
        $platform = Platform::tryFrom($platform) ?? Platform::Http;
        $origin = $this->requests->getCurrentRequest()?->getSchemeAndHttpHost() ?? 'http://localhost:8000';
        $url = $origin.'/api/v1/ingest/logs';

        $vectorSink = [
            '  watchdog:',
            '    type: http',
            \sprintf('    inputs: [%s]', Platform::Monolog === $platform ? 'app_json' : 'app'),
            '    uri: '.$url,
            '    compression: gzip',
            '    encoding:',
            '      codec: json',
            '    framing:',
            '      method: newline_delimited',
            '    auth:',
            '      strategy: bearer',
            \sprintf('      token: "%s"', $token),
            '    batch:',
            '      max_events: 1000',
            '      max_bytes: 1000000',
        ];

        $lines = match ($platform) {
            Platform::Http => [
                \sprintf('curl -X POST %s \\', $url),
                \sprintf("  -H 'Authorization: Bearer %s' \\", $token),
                "  -d '[{\"level\": \"info\", \"message\": \"Pierwszy log z Watchdoga\"}]'",
            ],
            Platform::Vector => [
                '# vector.yaml',
                'sources:',
                '  app:',
                '    type: file',
                '    include: [/var/log/app/*.log]',
                '',
                'sinks:',
                ...$vectorSink,
            ],
            Platform::FluentBit => $this->fluentBit($url, $token),
            Platform::Monolog => [
                '# config/packages/monolog.yaml: JSON lines to a file',
                'monolog:',
                '  handlers:',
                '    main:',
                '      type: stream',
                '      path: "%kernel.logs_dir%/app.json.log"',
                '      level: info',
                '      formatter: monolog.formatter.json',
                '',
                '# vector.yaml: ships the file, keeping the time from the app',
                'sources:',
                '  app_file:',
                '    type: file',
                '    include: [/path/to/app/var/log/app.json.log]',
                'transforms:',
                '  app_json:',
                '    type: remap',
                '    inputs: [app_file]',
                '    source: |',
                '      parsed, err = parse_json(.message)',
                '      if err == null && is_object(parsed) { . = merge(., object!(parsed)) }',
                '      if exists(.datetime) { .timestamp = .datetime }',
                'sinks:',
                ...$vectorSink,
            ],
        };

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function fluentBit(string $url, #[\SensitiveParameter] string $token): array
    {
        $parts = parse_url($url) ?: [];
        $https = 'https' === ($parts['scheme'] ?? 'http');

        return [
            '[OUTPUT]',
            '    Name             http',
            '    Match            *',
            '    Host             '.($parts['host'] ?? 'localhost'),
            '    Port             '.($parts['port'] ?? ($https ? 443 : 80)),
            '    tls              '.($https ? 'On' : 'Off'),
            '    URI              '.($parts['path'] ?? '/'),
            '    Format           json_lines',
            '    Compress         gzip',
            '    Header           Authorization Bearer '.$token,
            '    json_date_key    timestamp',
            '    json_date_format iso8601',
        ];
    }
}
