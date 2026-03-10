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
     */
    public function create(array $params): array
    {
        return $this->post('/access/policies', $params);
    }

    /**
     * Retrieve a policy by ID.
     */
    public function retrieve(string $policyId): array
    {
        return $this->get('/access/policies/' . rawurlencode($policyId));
    }

    /**
     * List policies with optional name filter and cursor pagination.
     *
     * @param array{name?: string, limit?: int, after?: string}|null $params
     */
    public function list(?array $params = null): array
    {
        return $this->get('/access/policies', $params);
    }

    /**
     * Update a policy.
     *
     * @param array{name?: string, description?: string, source_ids?: string[], row_filters?: array<array{column: string, values: string[]}>} $params
     */
    public function update(string $policyId, array $params): array
    {
        return $this->patch('/access/policies/' . rawurlencode($policyId), $params);
    }

    /**
     * Delete a policy.
     */
    public function del(string $policyId): array
    {
        return $this->delete('/access/policies/' . rawurlencode($policyId));
    }

    /**
     * Assign users to a policy.
     *
     * @param string[] $userIds
     */
    public function assignUsers(string $policyId, array $userIds): array
    {
        return $this->post('/access/policies/' . rawurlencode($policyId) . '/users', [
            'user_ids' => $userIds,
        ]);
    }

    /**
     * Remove a user from a policy.
     */
    public function removeUser(string $policyId, string $userId): array
    {
        return $this->delete('/access/policies/' . rawurlencode($policyId) . '/users/' . rawurlencode($userId));
    }

    /**
     * Resolve access for a user and data source.
     */
    public function resolve(string $userId, string $sourceId): array
    {
        return $this->post('/access/resolve', [
            'user_id' => $userId,
            'source_id' => $sourceId,
        ]);
    }

    /**
     * List columns for a data source.
     */
    public function columns(?string $sourceId = null): array
    {
        return $this->get('/access/columns', [
            'source_id' => $sourceId,
        ]);
    }
}
