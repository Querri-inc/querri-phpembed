<?php

declare(strict_types=1);

namespace Querri\Embed\Http;

/**
 * Retry logic for transient API errors. Matches the JS SDK's retry behavior:
 * exponential backoff with jitter, 429 always retried, 5xx only for idempotent methods.
 */
final class RetryStrategy
{
    private const array IDEMPOTENT_METHODS = ['GET', 'PUT', 'DELETE', 'HEAD', 'OPTIONS'];
    private const int MAX_DELAY_MS = 30_000;  // 30s ceiling
    private const int BASE_DELAY_MS = 500;    // Starting delay

    public static function isIdempotent(string $method): bool
    {
        return in_array(strtoupper($method), self::IDEMPOTENT_METHODS, true);
    }

    /**
     * 429 always retries (rate limit). 5xx retries only for idempotent methods
     * (safe to repeat without side effects).
     */
    public static function shouldRetry(int $statusCode, bool $isIdempotent): bool
    {
        if ($statusCode === 429) {
            return true;
        }

        if (in_array($statusCode, [500, 502, 503, 504], true) && $isIdempotent) {
            return true;
        }

        return false;
    }

    /**
     * Calculate retry delay in milliseconds with exponential backoff and jitter.
     *
     * Matches the JS SDK: base=500ms, exponential=base*2^(attempt-1), jitter=±25%.
     */
    public static function calculateDelay(int $attempt, ?float $retryAfterSeconds = null): int
    {
        $exponential = self::BASE_DELAY_MS * (2 ** ($attempt - 1));
        // ±25% jitter prevents thundering herd when multiple clients retry simultaneously
        $jitter = $exponential * 0.25 * (mt_rand() / mt_getrandmax() * 2 - 1);
        $delay = $exponential + $jitter;

        if ($retryAfterSeconds !== null && $retryAfterSeconds > 0) {
            $delay = max($delay, $retryAfterSeconds * 1000);
        }

        return (int) min($delay, self::MAX_DELAY_MS);
    }

    /**
     * Parse Retry-After header value (numeric seconds).
     * Symfony returns headers as array<string, string[]>, so we check [0] first.
     */
    public static function getRetryAfter(array $headers): ?float
    {
        $value = $headers['retry-after'][0] ?? $headers['retry-after'] ?? null;
        if ($value === null) {
            return null;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_FLOAT);
        return $parsed !== false ? $parsed : null;
    }
}
