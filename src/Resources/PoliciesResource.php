<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Access policies API — manage row-level security policies and user assignments.
 * All user-provided IDs in URL paths are RFC 3986 encoded via rawurlencode().
 */
final class PoliciesResource extends BaseResource
{
    /**
     * Create an access policy.
     *
     * @param array{name: string, description?: string, source_ids?: string[], row_filters?: array<array{column: string, values: string[]}>} $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->post('/access/policies', $params);
    }

    /**
     * Retrieve a policy by ID.
     *
     * @return array<string, mixed>
     */
    public function retrieve(string $policyId): array
    {
        return $this->get('/access/policies/' . rawurlencode($policyId));
    }

    /**
     * List policies with optional name filter and cursor pagination.
     *
     * @param array{name?: string, limit?: int, after?: string}|null $params
     * @return array{data: array<int, array<string, mixed>>, has_more: bool, next_cursor: string|null}
     */
    public function list(?array $params = null): array
    {
        return $this->get('/access/policies', $params);
    }

    /**
     * Update a policy.
     *
     * @param array{name?: string, description?: string, source_ids?: string[], row_filters?: array<array{column: string, values: string[]}>} $params
     * @return array<string, mixed>
     */
    public function update(string $policyId, array $params): array
    {
        return $this->patch('/access/policies/' . rawurlencode($policyId), $params);
    }

    /**
     * Delete a policy.
     *
     * @return array<string, mixed>
     */
    public function del(string $policyId): array
    {
        return $this->delete('/access/policies/' . rawurlencode($policyId));
    }

    /**
     * Assign users to a policy.
     *
     * Preferred: `$params = ['user_ids' => ['u_1', 'u_2']]`.
     * Legacy (deprecated since 0.2.0): passing a bare `string[]` list of
     * user IDs. The bare-list form is detected at runtime and wrapped into
     * the shape form; it will be removed in 0.3.0.
     *
     * @param array{user_ids: string[]}|string[] $params
     * @return array<string, mixed>
     */
    public function assignUsers(string $policyId, array $params): array
    {
        // Legacy: caller passed a bare list of user IDs (deprecated)
        if (array_is_list($params)) {
            $params = ['user_ids' => $params];
        }
        return $this->post('/access/policies/' . rawurlencode($policyId) . '/users', $params);
    }

    /**
     * Remove a user from a policy.
     *
     * @return array<string, mixed>
     */
    public function removeUser(string $policyId, string $userId): array
    {
        return $this->delete('/access/policies/' . rawurlencode($policyId) . '/users/' . rawurlencode($userId));
    }

    /**
     * Atomically replace ALL policy assignments for a user.
     *
     * Removes every existing assignment, then assigns exactly the listed
     * policies. Pass `['policy_ids' => []]` to remove all policies.
     *
     * Preferred: `$params = ['policy_ids' => ['pol_1', 'pol_2']]`.
     * Legacy (deprecated since 0.2.0): passing a bare `string[]` list of
     * policy IDs. The bare-list form is detected at runtime and wrapped
     * into the shape form; it will be removed in 0.3.0.
     *
     * @param array{policy_ids: string[]}|string[] $params
     * @return array<string, mixed>
     */
    public function replaceUserPolicies(string $userId, array $params): array
    {
        // Legacy: caller passed a bare list of policy IDs (deprecated)
        if (array_is_list($params)) {
            $params = ['policy_ids' => $params];
        }
        return $this->put('/access/users/' . rawurlencode($userId) . '/policies', $params);
    }

    /**
     * Resolve access for a user and data source. Returns the effective
     * row filters and column allowlist the user would see against this source.
     *
     * @return array<string, mixed>
     */
    public function resolveAccess(string $userId, string $sourceId): array
    {
        return $this->post('/access/resolve', [
            'user_id' => $userId,
            'source_id' => $sourceId,
        ]);
    }

    /**
     * List columns for a data source, annotated with the user's access.
     *
     * Note: this endpoint is not paginated (no cursor envelope). The API
     * returns a `{data: [...]}` wrapper that this method unwraps into a
     * bare list — the only list-style method in the SDK that doesn't
     * expose the full {data, has_more, next_cursor} envelope, because
     * has_more/next_cursor aren't meaningful here.
     *
     * @return array<int, array{source_id: string, source_name: string, columns: array<int, array<string, mixed>>}>
     */
    public function listColumns(?string $sourceId = null): array
    {
        $response = $this->get('/access/columns', [
            'source_id' => $sourceId,
        ]);
        return $response['data'];
    }

    // ─── Deprecated aliases (removed in 0.3.0) ──────────────────────

    /**
     * @deprecated since 0.2.0, removed in 0.3.0. Use resolveAccess() instead.
     * @return array<string, mixed>
     */
    public function resolve(string $userId, string $sourceId): array
    {
        return $this->resolveAccess($userId, $sourceId);
    }

    /**
     * @deprecated since 0.2.0, removed in 0.3.0. Use listColumns() instead.
     * @return array<int, array{source_id: string, source_name: string, columns: array<int, array<string, mixed>>}>
     */
    public function columns(?string $sourceId = null): array
    {
        return $this->listColumns($sourceId);
    }
}
