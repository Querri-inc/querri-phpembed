<?php

declare(strict_types=1);

namespace Querri\Embed\Exceptions;

/**
 * Thrown when a request exceeds the configured timeout.
 */
class TimeoutException extends ConnectionException
{
}
