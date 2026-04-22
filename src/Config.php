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
    public const VERSION = '0.1.5';

    private function __construct(
        public string $apiKey,
        public ?string $orgId,
        public string $baseUrl,
        public float $timeout,
        public int $maxRetries,
        public string $userAgent,
        public ?string $sessionToken = null,
    ) {
    }

    /**
     * Resolve config from explicit values, falling back to environment
     * variables (QUERRI_API_KEY, QUERRI_ORG_ID, QUERRI_URL), then defaults.
     */
    public static function resolve(
        ?string $apiKey = null,
        ?string $orgId = null,
        ?string $host = null,
        ?float $timeout = null,
        ?int $maxRetries = null,
    ): self {
        $apiKey ??= self::env('QUERRI_API_KEY');
        if ($apiKey === null || $apiKey === '') {
            throw new ConfigException(
                'API key is required. Pass it in the config or set the QUERRI_API_KEY environment variable.',
            );
        }

        $orgId ??= self::env('QUERRI_ORG_ID');
        $host ??= self::env('QUERRI_URL') ?? 'https://app.querri.com';
        $host = rtrim($host, '/');
        $baseUrl = str_ends_with($host, '/api/v1') ? $host : "{$host}/api/v1";

        return new self(
            apiKey: $apiKey,
            orgId: $orgId,
            baseUrl: $baseUrl,
            timeout: $timeout ?? 30.0,
            maxRetries: $maxRetries ?? 3,
            userAgent: 'querri-php/' . self::VERSION,
        );
    }

    /**
     * Create a session-based config for the internal API.
     * Uses X-Embed-Session auth and /api/ base URL instead of /api/v1/.
     */
    public static function forSession(
        string $sessionToken,
        ?string $host = null,
        ?float $timeout = null,
        ?int $maxRetries = null,
    ): self {
        $host ??= self::env('QUERRI_URL') ?? 'https://app.querri.com';
        $host = rtrim($host, '/');

        return new self(
            apiKey: '',
            orgId: null,
            baseUrl: "{$host}/api",
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
