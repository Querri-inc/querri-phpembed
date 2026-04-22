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
        $meta = self::extractMetadata($status, $body, $headers);

        $ra = self::readSingleHeader($headers, 'retry-after');
        $retryAfter = $ra !== null && is_numeric($ra) ? (float) $ra : null;

        return new static(
            message: $meta['message'],
            status: $status,
            body: $body,
            headers: $headers,
            requestId: $meta['requestId'],
            type: $meta['type'],
            code: $meta['errorCode'],
            docUrl: $meta['docUrl'],
            retryAfter: $retryAfter,
        );
    }
}
