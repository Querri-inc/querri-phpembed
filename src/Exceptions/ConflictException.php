<?php

declare(strict_types=1);

namespace Querri\Embed\Exceptions;

/**
 * Thrown on 409 — resource already exists (e.g., duplicate policy name).
 */
class ConflictException extends ApiException
{
}
