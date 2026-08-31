<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Read-only Dashboards surface for user-scoped (embed session) clients.
 *
 * Embed sessions are read-only for dashboards: the server excludes the
 * `admin:dashboards:write` scope from embed session auth, so create, update,
 * delete, and refresh are refused for this credential at the API. This class
 * exposes only the read endpoints so the refusal is visible at the type
 * level rather than as a runtime 403.
 */
final class UserDashboardsResource extends BaseResource
{
    /**
     * List dashboards the session user can access (FGA-filtered).
     *
     * @param array{limit?: int, after?: string}|null $params
     * @return array{data: array<int, array<string, mixed>>, has_more: bool, next_cursor: string|null}
     */
    public function list(?array $params = null): array
    {
        return $this->get('/dashboards', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieve(string $dashboardId): array
    {
        return $this->get('/dashboards/' . rawurlencode($dashboardId));
    }

    /**
     * Poll the status of a refresh started elsewhere (read-only; embed
     * sessions cannot start a refresh themselves).
     *
     * @return array<string, mixed>
     */
    public function refreshStatus(string $dashboardId): array
    {
        return $this->get('/dashboards/' . rawurlencode($dashboardId) . '/refresh/status');
    }
}
