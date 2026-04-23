<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Typed constants for the permission values accepted by SharingResource.
 *
 * Kept as a final class with string constants (not a PHP enum) so API
 * serialization stays a plain string — no `->value` unwrap at call sites.
 */
final class SharingPermission
{
    public const VIEW = 'view';
    public const EDIT = 'edit';

    /** @codeCoverageIgnore */
    private function __construct()
    {
    }
}
