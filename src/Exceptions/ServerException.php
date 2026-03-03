<?php

declare(strict_types=1);

namespace Querri\Embed\Exceptions;

/**
 * Thrown on 5xx — server-side error. Auto-retried for idempotent requests.
 */
class ServerException extends ApiException
{
}
