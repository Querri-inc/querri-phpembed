<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Dashboards API — create, list, retrieve, update, delete, and refresh dashboards.
 */
final class DashboardsResource extends BaseResource
{
    public function list(): array
    {
        return $this->get('/dashboards');
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
