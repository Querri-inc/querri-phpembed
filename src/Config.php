<?php

declare(strict_types=1);

namespace Querri\Embed;

use Querri\Embed\Exceptions\ConfigException;

/**
 * Immutable SDK configuration. Use Config::resolve() to construct from
 * explicit values, environment variables, or defaults.
 */
final readonly class Config
{
    public const VERSION = '1.0.0';

    private function __construct(
        public string $apiKey,
        public ?string $orgId,
        public string $host,
        public string $baseUrl,
        public float $timeout,
        public int $maxRetries,
        public string $userAgent,
        public ?string $sessionToken = null,
        public ?string $defaultOrigin = null,
    ) {
    }

    /**
     * Resolve config from explicit values, falling back to environment
     * variables (QUERRI_API_KEY, QUERRI_ORG_ID, QUERRI_URL,
     * QUERRI_EMBED_ORIGIN), then defaults.
     */
    public static function resolve(
        ?string $apiKey = null,
        ?string $orgId = null,
        ?string $host = null,
        ?float $timeout = null,
        ?int $maxRetries = null,
        ?string $defaultOrigin = null,
    ): self {
        $apiKey ??= self::env('QUERRI_API_KEY');
        if ($apiKey === null || $apiKey === '') {
            throw new ConfigException(
                'API key is required. Pass it in the config or set the QUERRI_API_KEY environment variable.',
            );
        }

        $orgId ??= self::env('QUERRI_ORG_ID');
        if ($orgId === null || $orgId === '') {
            throw new ConfigException(
                'Organization ID is required — the API rejects every request without an X-Tenant-ID header. '
                . 'Pass org_id in the config or set the QUERRI_ORG_ID environment variable.',
            );
        }

        $defaultOrigin ??= self::env('QUERRI_EMBED_ORIGIN');
        $host ??= self::env('QUERRI_URL') ?? 'https://app.querri.com';
        $host = rtrim($host, '/');
        $baseUrl = str_ends_with($host, '/api/v1') ? $host : "{$host}/api/v1";
        // Strip any API-path suffix from host so consumers (e.g. UserQuerriClient)
        // can use it as a bare origin without re-parsing baseUrl.
        $bareHost = preg_replace('#/api(/v1)?$#', '', $host) ?? $host;

        return new self(
            apiKey: $apiKey,
            orgId: $orgId,
            host: $bareHost,
            baseUrl: $baseUrl,
            timeout: $timeout ?? 30.0,
            maxRetries: $maxRetries ?? 3,
            userAgent: 'querri-php/' . self::VERSION,
            defaultOrigin: $defaultOrigin,
        );
    }

    /**
     * Create a session-based config for user-scoped calls.
     * Uses X-Embed-Session auth against the same /api/v1 public API —
     * embed sessions are priority-0 auth on v1, so the base URL matches
     * the API-key client and no X-Tenant-ID header is needed.
     */
    public static function forSession(
        string $sessionToken,
        ?string $host = null,
        ?float $timeout = null,
        ?int $maxRetries = null,
    ): self {
        $host ??= self::env('QUERRI_URL') ?? 'https://app.querri.com';
        $host = rtrim($host, '/');
        $bareHost = preg_replace('#/api(/v1)?$#', '', $host) ?? $host;

        return new self(
            apiKey: '',
            orgId: null,
            host: $bareHost,
            baseUrl: "{$bareHost}/api/v1",
            timeout: $timeout ?? 30.0,
            maxRetries: $maxRetries ?? 3,
            userAgent: 'querri-php/' . self::VERSION,
            sessionToken: $sessionToken,
        );
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return $value;
        }

        // Fallback chain: getenv() → $_ENV → $_SERVER (covers CLI, Apache, nginx+FPM)
        return $_ENV[$name] ?? $_SERVER[$name] ?? null;
    }
}
