<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Usage API — organization and per-user usage metrics.
 */
final class UsageResource extends BaseResource
{
    /**
     * Get organization-level usage.
     *
     * Preferred: `$params = ['period' => 'current_month']`.
     * Legacy (deprecated since 0.2.0): passing `period` as a bare string.
     * The string form is detected at runtime and wrapped; will be removed
     * in 0.3.0.
     *
     * @param string|array{period?: string} $params Optional period filter:
     *   "current_month" | "last_month" | "last_30_days"
     * @return array<string, mixed>
     */
    public function getOrgUsage(string|array $params = []): array
    {
        if (is_string($params)) {
            $params = ['period' => $params];
        }
        $params['period'] ??= 'current_month';
        return $this->get('/usage', $params);
    }

    /**
     * Get per-user usage.
     *
     * Preferred: `$params = ['period' => 'current_month']`.
     * Legacy (deprecated since 0.2.0): passing `period` as a bare string.
     *
     * @param string|array{period?: string} $params
     * @return array<string, mixed>
     */
    public function getUserUsage(string $userId, string|array $params = []): array
    {
        if (is_string($params)) {
            $params = ['period' => $params];
        }
        $params['period'] ??= 'current_month';
        return $this->get('/usage/users/' . rawurlencode($userId), $params);
    }
}
