<?php

declare(strict_types=1);

namespace Querri\Embed\Exceptions;

/**
 * Thrown on network failures. Auto-retried for idempotent requests (GET, PUT, DELETE).
 */
class ConnectionException extends QuerriException
{
}
