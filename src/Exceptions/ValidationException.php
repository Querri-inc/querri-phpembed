<?php

declare(strict_types=1);

namespace Querri\Embed\Exceptions;

/**
 * Thrown on 400 or 422 — invalid request parameters.
 *
 * Also thrown client-side (before any HTTP call) when the SDK can prove a
 * request would be rejected, e.g. an embed-session ttl outside [900, 86400]
 * or an origin longer than 500 characters.
 */
class ValidationException extends ApiException
{
}
