<?php

declare(strict_types=1);

namespace Watchdog\Tests\Unit\Logging\UI;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Watchdog\Logging\UI\Twig\Snippets;

final class SnippetsTest extends TestCase
{
    private function snippets(string $url): Snippets
    {
        $requests = new RequestStack();
        $requests->push(Request::create($url));

        return new Snippets($requests);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function platforms(): iterable
    {
        foreach (['http', 'vector', 'fluent-bit', 'monolog'] as $platform) {
            yield $platform => [$platform];
        }
    }

    #[DataProvider('platforms')]
    public function testThePlatformSetupHoldsTheToken(string $platform): void
    {
        self::assertStringContainsString('wd_secret', $this->snippets('https://logs.example.com/')->snippet($platform, 'wd_secret'));
    }

    public function testEveryPlatformHasAPickerEntry(): void
    {
        self::assertSame(['http', 'vector', 'fluent-bit', 'monolog'], array_keys($this->snippets('http://x.test/')->platforms()));
    }

    public function testFluentBitPointsAtTheHostPortAndTlsOfTheApp(): void
    {
        $https = $this->snippets('https://logs.example.com/')->snippet('fluent-bit', 't');
        self::assertMatchesRegularExpression('/Host\s+logs\.example\.com/', $https);
        self::assertMatchesRegularExpression('/Port\s+443/', $https);
        self::assertMatchesRegularExpression('/tls\s+On/', $https);

        $local = $this->snippets('http://localhost:8000/')->snippet('fluent-bit', 't');
        self::assertMatchesRegularExpression('/Port\s+8000/', $local);
        self::assertMatchesRegularExpression('/tls\s+Off/', $local);
    }

    public function testMonologFeedsTheTransformNotTheRawFileIntoTheSink(): void
    {
        $snippets = $this->snippets('https://x.test/');

        self::assertStringContainsString('inputs: [app_json]', $snippets->snippet('monolog', 't'));
        self::assertStringContainsString('inputs: [app]', $snippets->snippet('vector', 't'));
    }

    public function testTheCurlExampleUsesTheIngestUrl(): void
    {
        self::assertStringContainsString('https://x.test/api/v1/ingest/logs', $this->snippets('https://x.test/anything')->snippet('http', 't'));
    }
}
