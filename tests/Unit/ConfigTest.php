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
        foreach (['QUERRI_API_KEY', 'QUERRI_ORG_ID', 'QUERRI_URL', 'QUERRI_EMBED_ORIGIN'] as $name) {
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
        $config = Config::resolve(orgId: 'org_123');
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

    public function testResolveThrowsWhenOrgIdMissing(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Organization ID is required');
        Config::resolve(apiKey: 'k');
    }

    public function testResolveThrowsOnEmptyStringOrgId(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Organization ID is required');
        Config::resolve(apiKey: 'k', orgId: '');
    }

    public function testResolveFallsBackToEnvOrgId(): void
    {
        putenv('QUERRI_ORG_ID=org_env');
        $config = Config::resolve(apiKey: 'k');
        $this->assertSame('org_env', $config->orgId);
    }

    public function testResolveDefaultsTimeoutAndRetries(): void
    {
        $config = Config::resolve(apiKey: 'k', orgId: 'org_x');
        $this->assertSame(30.0, $config->timeout);
        $this->assertSame(3, $config->maxRetries);
    }

    public function testResolveHonorsExplicitTimeoutAndRetries(): void
    {
        $config = Config::resolve(apiKey: 'k', orgId: 'org_x', timeout: 5.0, maxRetries: 1);
        $this->assertSame(5.0, $config->timeout);
        $this->assertSame(1, $config->maxRetries);
    }

    public function testResolveSetsUserAgentWithVersion(): void
    {
        $config = Config::resolve(apiKey: 'k', orgId: 'org_x');
        $this->assertSame('querri-php/' . Config::VERSION, $config->userAgent);
    }

    public function testResolveLeavesSessionTokenNull(): void
    {
        $config = Config::resolve(apiKey: 'k', orgId: 'org_x');
        $this->assertNull($config->sessionToken);
    }

    public function testResolveDefaultBaseUrl(): void
    {
        $config = Config::resolve(apiKey: 'k', orgId: 'org_x');
        $this->assertSame('https://app.querri.com/api/v1', $config->baseUrl);
    }

    public function testResolveReadsQuerriUrlFromEnv(): void
    {
        putenv('QUERRI_URL=https://custom.example.com');
        $config = Config::resolve(apiKey: 'k', orgId: 'org_x');
        $this->assertSame('https://custom.example.com/api/v1', $config->baseUrl);
    }

    public function testResolveDefaultOriginDefaultsToNull(): void
    {
        $config = Config::resolve(apiKey: 'k', orgId: 'org_x');
        $this->assertNull($config->defaultOrigin);
    }

    public function testResolveUsesExplicitDefaultOrigin(): void
    {
        $config = Config::resolve(apiKey: 'k', orgId: 'org_x', defaultOrigin: 'https://myapp.example');
        $this->assertSame('https://myapp.example', $config->defaultOrigin);
    }

    public function testResolveFallsBackToEnvEmbedOrigin(): void
    {
        putenv('QUERRI_EMBED_ORIGIN=https://env-app.example');
        $config = Config::resolve(apiKey: 'k', orgId: 'org_x');
        $this->assertSame('https://env-app.example', $config->defaultOrigin);
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
        $config = Config::resolve(apiKey: 'k', orgId: 'org_x', host: $input);
        $this->assertSame($expected, $config->baseUrl);
    }

    public function testResolveHostEndingInApiSlashNormalizes(): void
    {
        $config = Config::resolve(apiKey: 'k', orgId: 'org_x', host: 'https://example.com/api/');
        $this->assertSame('https://example.com/api/v1', $config->baseUrl);
        $this->assertSame('https://example.com', $config->host);
    }

    public function testForSessionUsesV1BaseUrl(): void
    {
        // Embed sessions authenticate on the public /api/v1 API via
        // X-Embed-Session (priority-0 auth), not the internal /api/ paths.
        $config = Config::forSession(sessionToken: 'sess_abc', host: 'https://example.com');
        $this->assertSame('https://example.com/api/v1', $config->baseUrl);
    }

    public function testHostIsStoredBareForConsumers(): void
    {
        $config = Config::resolve(apiKey: 'k', orgId: 'org_x', host: 'https://example.com');
        $this->assertSame('https://example.com', $config->host);

        // host should also be bare even when the caller supplied an API path suffix
        $withSuffix = Config::resolve(apiKey: 'k', orgId: 'org_x', host: 'https://example.com/api/v1');
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
