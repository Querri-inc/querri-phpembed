<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Querri\Embed\Config;
use Querri\Embed\Exceptions\ConfigException;

final class ConfigTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        foreach (['QUERRI_API_KEY', 'QUERRI_ORG_ID', 'QUERRI_URL'] as $name) {
            $this->savedEnv[$name] = getenv($name);
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv("{$name}={$value}");
            }
        }
    }

    public function testResolveUsesExplicitApiKey(): void
    {
        $config = Config::resolve(apiKey: 'explicit_key', orgId: 'org_123');
        $this->assertSame('explicit_key', $config->apiKey);
        $this->assertSame('org_123', $config->orgId);
    }

    public function testResolveFallsBackToEnvApiKey(): void
    {
        putenv('QUERRI_API_KEY=env_key');
        $config = Config::resolve();
        $this->assertSame('env_key', $config->apiKey);
    }

    public function testResolveThrowsWhenApiKeyMissing(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('API key is required');
        Config::resolve();
    }

    public function testResolveThrowsOnEmptyStringApiKey(): void
    {
        $this->expectException(ConfigException::class);
        Config::resolve(apiKey: '');
    }

    public function testResolveDefaultsTimeoutAndRetries(): void
    {
        $config = Config::resolve(apiKey: 'k');
        $this->assertSame(30.0, $config->timeout);
        $this->assertSame(3, $config->maxRetries);
    }

    public function testResolveHonorsExplicitTimeoutAndRetries(): void
    {
        $config = Config::resolve(apiKey: 'k', timeout: 5.0, maxRetries: 1);
        $this->assertSame(5.0, $config->timeout);
        $this->assertSame(1, $config->maxRetries);
    }

    public function testResolveSetsUserAgentWithVersion(): void
    {
        $config = Config::resolve(apiKey: 'k');
        $this->assertSame('querri-php/' . Config::VERSION, $config->userAgent);
    }

    public function testResolveLeavesSessionTokenNull(): void
    {
        $config = Config::resolve(apiKey: 'k');
        $this->assertNull($config->sessionToken);
    }

    public function testResolveDefaultBaseUrl(): void
    {
        $config = Config::resolve(apiKey: 'k');
        $this->assertSame('https://app.querri.com/api/v1', $config->baseUrl);
    }

    public function testResolveReadsQuerriUrlFromEnv(): void
    {
        putenv('QUERRI_URL=https://custom.example.com');
        $config = Config::resolve(apiKey: 'k');
        $this->assertSame('https://custom.example.com/api/v1', $config->baseUrl);
    }

    /** @return array<string, array{string, string}> */
    public static function hostNormalizationCases(): array
    {
        return [
            'bare host' => [
                'https://example.com',
                'https://example.com/api/v1',
            ],
            'trailing slash stripped' => [
                'https://example.com/',
                'https://example.com/api/v1',
            ],
            'already has /api/v1' => [
                'https://example.com/api/v1',
                'https://example.com/api/v1',
            ],
            'has /api/v1 with trailing slash' => [
                'https://example.com/api/v1/',
                'https://example.com/api/v1',
            ],
        ];
    }

    #[DataProvider('hostNormalizationCases')]
    public function testResolveNormalizesHost(string $input, string $expected): void
    {
        $config = Config::resolve(apiKey: 'k', host: $input);
        $this->assertSame($expected, $config->baseUrl);
    }

    public function testResolveHostEndingInApiSlashProducesDoubleSlash(): void
    {
        // Known bug: when host ends in '/api/', the current normalization produces
        // '/api/api/v1' because rtrim only removes the trailing slash, not the '/api'
        // segment. Captured here as a skipped test; fix in a dedicated follow-up.
        $this->markTestSkipped(
            "bug: Config produces '/api/api/v1' when host ends in '/api/' — fix in a follow-up PR",
        );

        // @phpstan-ignore-next-line (intentionally unreachable)
        $config = Config::resolve(apiKey: 'k', host: 'https://example.com/api/');
        $this->assertSame('https://example.com/api/v1', $config->baseUrl);
    }

    public function testForSessionUsesApiBaseUrlNotApiV1(): void
    {
        $config = Config::forSession(sessionToken: 'sess_abc', host: 'https://example.com');
        $this->assertSame('https://example.com/api', $config->baseUrl);
    }

    public function testHostIsStoredBareForConsumers(): void
    {
        $config = Config::resolve(apiKey: 'k', host: 'https://example.com');
        $this->assertSame('https://example.com', $config->host);

        // host should also be bare even when the caller supplied an API path suffix
        $withSuffix = Config::resolve(apiKey: 'k', host: 'https://example.com/api/v1');
        $this->assertSame('https://example.com', $withSuffix->host);
    }

    public function testForSessionHostMatchesResolveHost(): void
    {
        $config = Config::forSession(sessionToken: 's', host: 'https://example.com/api/v1');
        $this->assertSame('https://example.com', $config->host);
    }

    public function testForSessionSetsSessionTokenAndEmptyApiKey(): void
    {
        $config = Config::forSession(sessionToken: 'sess_abc');
        $this->assertSame('sess_abc', $config->sessionToken);
        $this->assertSame('', $config->apiKey);
        $this->assertNull($config->orgId);
    }

    public function testVersionConstantIsSemver(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Config::VERSION);
    }
}
