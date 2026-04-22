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
     * @param string[] $userIds
     * @return array<string, mixed>
     */
    public function assignUsers(string $policyId, array $userIds): array
    {
        return $this->post('/access/policies/' . rawurlencode($policyId) . '/users', [
            'user_ids' => $userIds,
        ]);
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
     * policies. Pass an empty array to remove all policies.
     *
     * @param string[] $policyIds
     * @return array<string, mixed>
     */
    public function replaceUserPolicies(string $userId, array $policyIds): array
    {
        return $this->put('/access/users/' . rawurlencode($userId) . '/policies', [
            'policy_ids' => $policyIds,
        ]);
    }

    /**
     * Resolve access for a user and data source.
     *
     * @return array<string, mixed>
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
     *
     * @return array<int, array{source_id: string, source_name: string, columns: array<int, array<string, mixed>>}>
     */
    public function columns(?string $sourceId = null): array
    {
        $response = $this->get('/access/columns', [
            'source_id' => $sourceId,
        ]);
        return $response['data'] ?? $response;
    }
}
