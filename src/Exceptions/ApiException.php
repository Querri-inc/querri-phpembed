<?php

declare(strict_types=1);

namespace Querri\Embed\Exceptions;

/**
 * Thrown when the Querri API returns an HTTP error response (status >= 400).
 *
 * Carries the full response context: status code, parsed body, headers,
 * request ID, and optional error metadata (type, code, doc_url).
 */
class ApiException extends QuerriException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly mixed $body = null,
        public readonly array $headers = [],
        public readonly ?string $requestId = null,
        public readonly ?string $type = null,
        public readonly ?string $code = null,
        public readonly ?string $docUrl = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * Create from an HTTP response. Extracts message, request ID, and error
     * metadata (type, code, doc_url) from the response body.
     */
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

        return new static(
            message: $message,
            status: $status,
            body: $body,
            headers: $headers,
            requestId: $requestId,
            type: $type,
            code: $code,
            docUrl: $docUrl,
        );
    }

    /**
     * Map an HTTP status code to a specific exception subclass and throw it.
     * Does nothing for status < 400.
     */
    public static function raiseForStatus(int $status, mixed $body, array $headers): void
    {
        if ($status < 400) {
            return;
        }

        $exception = match ($status) {
            400 => ValidationException::fromResponse($status, $body, $headers),
            401 => AuthenticationException::fromResponse($status, $body, $headers),
            403 => PermissionException::fromResponse($status, $body, $headers),
            404 => NotFoundException::fromResponse($status, $body, $headers),
            409 => ConflictException::fromResponse($status, $body, $headers),
            429 => RateLimitException::fromResponse($status, $body, $headers),
            default => $status >= 500
                ? ServerException::fromResponse($status, $body, $headers)
                : self::fromResponse($status, $body, $headers),
        };

        throw $exception;
    }

    /**
     * Extract a human-readable message from the API response body.
     * Checks body.error (string), body.message, body.error.message, in that order.
     */
    protected static function extractMessage(int $status, mixed $body): string
    {
        if (is_array($body)) {
            if (is_string($body['error'] ?? null)) {
                return $body['error'];
            }
            if (is_string($body['message'] ?? null)) {
                return $body['message'];
            }
            if (is_array($body['error'] ?? null) && is_string($body['error']['message'] ?? null)) {
                return $body['error']['message'];
            }
        }

        return "Request failed with status {$status}";
    }
}
