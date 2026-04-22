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
    /**
     * @param array<string, array<int, string>|string> $headers
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly mixed $body = null,
        public readonly array $headers = [],
        public readonly ?string $requestId = null,
        public readonly ?string $type = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $docUrl = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * Create from an HTTP response. Extracts message, request ID, and error
     * metadata (type, code, doc_url) from the response body.
     *
     * Supports both the legacy flat format and the Stripe-style nested format:
     *   {"error": {"type": "...", "code": "...", "message": "...", "request_id": "req_..."}}
     *
     * @param array<string, array<int, string>|string> $headers
     */
    public static function fromResponse(int $status, mixed $body, array $headers): static
    {
        $message = self::extractMessage($status, $body);
        $requestId = $headers['x-request-id'][0] ?? $headers['x-request-id'] ?? null;
        $type = null;
        $errorCode = null;
        $docUrl = null;

        if (is_array($body)) {
            // Stripe-style nested error object (primary format)
            $error = is_array($body['error'] ?? null) ? $body['error'] : null;
            $source = $error ?? $body;

            $type = is_string($source['type'] ?? null) ? $source['type'] : null;
            $errorCode = is_string($source['code'] ?? null) ? $source['code'] : null;
            $docUrl = is_string($source['doc_url'] ?? null) ? $source['doc_url'] : null;

            // Fall back to body request_id when header is absent
            if ($requestId === null && $error !== null) {
                $requestId = is_string($error['request_id'] ?? null) ? $error['request_id'] : null;
            }
        }

        return new static(
            message: $message,
            status: $status,
            body: $body,
            headers: $headers,
            requestId: $requestId,
            type: $type,
            errorCode: $errorCode,
            docUrl: $docUrl,
        );
    }

    /**
     * Map an HTTP status code to a specific exception subclass and throw it.
     * Does nothing for status < 400.
     *
     * @param array<string, array<int, string>|string> $headers
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
     * Prefers nested error.message (Stripe-style), then flat body.message, then body.error (string).
     */
    protected static function extractMessage(int $status, mixed $body): string
    {
        if (is_array($body)) {
            if (is_array($body['error'] ?? null) && is_string($body['error']['message'] ?? null)) {
                return $body['error']['message'];
            }
            if (is_string($body['message'] ?? null)) {
                return $body['message'];
            }
            if (is_string($body['error'] ?? null)) {
                return $body['error'];
            }
        }

        return "Request failed with status {$status}";
    }
}
