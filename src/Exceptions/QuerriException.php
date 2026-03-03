<?php

declare(strict_types=1);

namespace Querri\Embed\Exceptions;

/**
 * Base exception for all Querri SDK errors.
 *
 * Exception hierarchy:
 *   QuerriException
 *   ├── ConfigException          — missing/invalid configuration
 *   ├── ConnectionException      — network failures (auto-retried for idempotent requests)
 *   │   └── TimeoutException     — request timeout exceeded
 *   └── ApiException             — HTTP error responses from the API
 *       ├── ValidationException  — 400
 *       ├── AuthenticationException — 401
 *       ├── PermissionException  — 403
 *       ├── NotFoundException    — 404
 *       ├── ConflictException    — 409
 *       ├── RateLimitException   — 429 (auto-retried)
 *       └── ServerException      — 5xx (auto-retried for idempotent requests)
 */
class QuerriException extends \RuntimeException
{
}
