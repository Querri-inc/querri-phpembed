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
     * @return array{data: array<int, array<string, mixed>>, has_more: bool, next_cursor: string|null}
     */
    public function list(?array $params = null): array
    {
        return $this->get('/dashboards', $params);
    }

    /**
     * @param array{name: string, description?: string} $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->post('/dashboards', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieve(string $dashboardId): array
    {
        return $this->get('/dashboards/' . rawurlencode($dashboardId));
    }

    /**
     * @param array{name?: string, description?: string} $params
     * @return array<string, mixed>
     */
    public function update(string $dashboardId, array $params): array
    {
        return $this->patch('/dashboards/' . rawurlencode($dashboardId), $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function del(string $dashboardId): array
    {
        return $this->delete('/dashboards/' . rawurlencode($dashboardId));
    }

    /**
     * @return array<string, mixed>
     */
    public function refresh(string $dashboardId): array
    {
        return $this->post('/dashboards/' . rawurlencode($dashboardId) . '/refresh');
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshStatus(string $dashboardId): array
    {
        return $this->get('/dashboards/' . rawurlencode($dashboardId) . '/refresh/status');
    }
}
