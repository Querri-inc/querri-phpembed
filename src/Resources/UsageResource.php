<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Usage API — organization and per-user usage metrics.
 */
final class UsageResource extends BaseResource
{
    /**
     * @param string $period "current_month"|"last_month"|"last_30_days"
     */
    public function getOrgUsage(string $period = 'current_month'): array
    {
        return $this->get('/usage', ['period' => $period]);
    }

    /**
     * @param string $period "current_month"|"last_month"|"last_30_days"
     */
    public function getUserUsage(string $userId, string $period = 'current_month'): array
    {
        return $this->get('/usage/users/' . rawurlencode($userId), ['period' => $period]);
    }
}
