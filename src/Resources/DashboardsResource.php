<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Dashboards API — create, list, retrieve, update, delete, and refresh dashboards.
 */
final class DashboardsResource extends BaseResource
{
    /**
     * List dashboards. Optionally filter by user (FGA-filtered).
     *
     * @param array{user_id?: string, limit?: int, after?: string}|null $params
     *   user_id: WorkOS user ID or external ID — returns only dashboards the user can access
     */
    public function list(?array $params = null): array
    {
        return $this->get('/dashboards', $params);
    }

    /**
     * @param array{name: string, description?: string} $params
     */
    public function create(array $params): array
    {
        return $this->post('/dashboards', $params);
    }

    public function retrieve(string $dashboardId): array
    {
        return $this->get('/dashboards/' . rawurlencode($dashboardId));
    }

    /**
     * @param array{name?: string, description?: string} $params
     */
    public function update(string $dashboardId, array $params): array
    {
        return $this->patch('/dashboards/' . rawurlencode($dashboardId), $params);
    }

    public function del(string $dashboardId): array
    {
        return $this->delete('/dashboards/' . rawurlencode($dashboardId));
    }

    public function refresh(string $dashboardId): array
    {
        return $this->post('/dashboards/' . rawurlencode($dashboardId) . '/refresh');
    }

    public function refreshStatus(string $dashboardId): array
    {
        return $this->get('/dashboards/' . rawurlencode($dashboardId) . '/refresh/status');
    }
}
