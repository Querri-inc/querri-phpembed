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

    public static function fromResponse(int $status, mixed $body, array $headers): static
    {
        $message = self::extractMessage($status, $body);
        $requestId = $headers['x-request-id'][0] ?? $headers['x-request-id'] ?? null;
        $type = null;
        $code = null;
        $docUrl = null;

        if (is_array($body)) {
            $type = is_string($body['type'] ?? null) ? $body['type'] : null;
            $code = is_string($body['code'] ?? null) ? $body['code'] : null;
            $docUrl = is_string($body['doc_url'] ?? null) ? $body['doc_url'] : null;
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
