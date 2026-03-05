<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Sharing / Permissions API — grant, revoke, and list access to projects and dashboards.
 */
final class SharingResource extends BaseResource
{
    // ─── Projects ────────────────────────────────────────

    /**
     * @param array{user_id: string, permission?: string} $params  permission: "view"|"edit"
     */
    public function shareProject(string $projectId, array $params): array
    {
        return $this->post('/projects/' . rawurlencode($projectId) . '/shares', $params);
    }

    public function revokeProjectShare(string $projectId, string $userId): array
    {
        return $this->delete(
            '/projects/' . rawurlencode($projectId) . '/shares/' . rawurlencode($userId),
        );
    }

    public function listProjectShares(string $projectId): array
    {
        return $this->get('/projects/' . rawurlencode($projectId) . '/shares');
    }

    // ─── Dashboards ──────────────────────────────────────

    /**
     * @param array{user_id: string, permission?: string} $params  permission: "view"|"edit"
     */
    public function shareDashboard(string $dashboardId, array $params): array
    {
        return $this->post('/dashboards/' . rawurlencode($dashboardId) . '/shares', $params);
    }

    public function revokeDashboardShare(string $dashboardId, string $userId): array
    {
        return $this->delete(
            '/dashboards/' . rawurlencode($dashboardId) . '/shares/' . rawurlencode($userId),
        );
    }

    public function listDashboardShares(string $dashboardId): array
    {
        return $this->get('/dashboards/' . rawurlencode($dashboardId) . '/shares');
    }
}
