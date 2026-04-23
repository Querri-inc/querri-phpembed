<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Sharing / Permissions API — grant, revoke, and list access to projects, dashboards, and sources.
 *
 * The `permission` value on share operations is a plain string; use the
 * {@see SharingPermission} constants (`SharingPermission::VIEW`,
 * `SharingPermission::EDIT`) rather than repeating the magic strings at
 * call sites.
 */
final class SharingResource extends BaseResource
{
    // ─── Projects ────────────────────────────────────────

    /**
     * @param array{user_id: string, permission?: string} $params
     *   permission: one of SharingPermission::VIEW | SharingPermission::EDIT
     * @return array<string, mixed>
     */
    public function shareProject(string $projectId, array $params): array
    {
        return $this->post('/projects/' . rawurlencode($projectId) . '/shares', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function revokeProjectShare(string $projectId, string $userId): array
    {
        return $this->delete(
            '/projects/' . rawurlencode($projectId) . '/shares/' . rawurlencode($userId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function listProjectShares(string $projectId): array
    {
        return $this->get('/projects/' . rawurlencode($projectId) . '/shares');
    }

    // ─── Dashboards ──────────────────────────────────────

    /**
     * @param array{user_id: string, permission?: string} $params
     *   permission: one of SharingPermission::VIEW | SharingPermission::EDIT
     * @return array<string, mixed>
     */
    public function shareDashboard(string $dashboardId, array $params): array
    {
        return $this->post('/dashboards/' . rawurlencode($dashboardId) . '/shares', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function revokeDashboardShare(string $dashboardId, string $userId): array
    {
        return $this->delete(
            '/dashboards/' . rawurlencode($dashboardId) . '/shares/' . rawurlencode($userId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function listDashboardShares(string $dashboardId): array
    {
        return $this->get('/dashboards/' . rawurlencode($dashboardId) . '/shares');
    }

    // ─── Sources ──────────────────────────────────────────

    /**
     * Grant access to a source.
     *
     * @param array{user_id: string, permission?: string} $params
     *   permission: one of SharingPermission::VIEW | SharingPermission::EDIT
     * @return array<string, mixed>
     */
    public function shareSource(string $sourceId, array $params): array
    {
        return $this->post('/sources/' . rawurlencode($sourceId) . '/shares', $params);
    }

    /**
     * Enable or disable org-wide sharing for a source.
     *
     * @param array{enabled: bool, permission?: string} $params
     *   permission: one of SharingPermission::VIEW | SharingPermission::EDIT
     * @return array<string, mixed>
     */
    public function orgShareSource(string $sourceId, array $params): array
    {
        return $this->post('/sources/' . rawurlencode($sourceId) . '/org-share', $params);
    }
}
