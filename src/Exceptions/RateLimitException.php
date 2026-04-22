<?php

declare(strict_types=1);

namespace Querri\Embed\Exceptions;

/**
 * Thrown on 429 Too Many Requests. The SDK auto-retries these with backoff.
 */
class RateLimitException extends ApiException
{
    /** Seconds until the client should retry, parsed from the Retry-After header. */
    public readonly ?float $retryAfter;

    /**
     * @param array<string, array<int, string>|string> $headers
     */
    public function __construct(
        string $message,
        int $status,
        mixed $body = null,
        array $headers = [],
        ?string $requestId = null,
        ?string $type = null,
        ?string $code = null,
        ?string $docUrl = null,
        ?float $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $body, $headers, $requestId, $type, $code, $docUrl, $previous);
        $this->retryAfter = $retryAfter;
    }

    /**
     * @param array<string, array<int, string>|string> $headers
     */
    public static function fromResponse(int $status, mixed $body, array $headers): static
    {
        $message = self::extractMessage($status, $body);
        $requestId = $headers['x-request-id'][0] ?? $headers['x-request-id'] ?? null;
        $type = null;
        $code = null;
        $docUrl = null;

        if (is_array($body)) {
            // Stripe-style nested error object (primary format)
            $error = is_array($body['error'] ?? null) ? $body['error'] : null;
            $source = $error ?? $body;

            $type = is_string($source['type'] ?? null) ? $source['type'] : null;
            $code = is_string($source['code'] ?? null) ? $source['code'] : null;
            $docUrl = is_string($source['doc_url'] ?? null) ? $source['doc_url'] : null;

            // Fall back to body request_id when header is absent
            if ($requestId === null && $error !== null) {
                $requestId = is_string($error['request_id'] ?? null) ? $error['request_id'] : null;
            }
        }

        $ra = $headers['retry-after'][0] ?? $headers['retry-after'] ?? null;
        $retryAfter = $ra !== null && is_numeric($ra) ? (float) $ra : null;

        return new static(
            message: $message,
            status: $status,
            body: $body,
            headers: $headers,
            requestId: $requestId,
            type: $type,
            code: $code,
            docUrl: $docUrl,
            retryAfter: $retryAfter,
        );
    }
}
