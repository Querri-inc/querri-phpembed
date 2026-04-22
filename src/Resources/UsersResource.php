<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Users API — create, retrieve, list, update, and delete Querri user accounts.
 * All user-provided IDs in URL paths are RFC 3986 encoded via rawurlencode().
 */
final class UsersResource extends BaseResource
{
    /**
     * Create a new user.
     *
     * @param array{email?: string, first_name?: string, last_name?: string, role?: string, external_id?: string} $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->post('/users', $params);
    }

    /**
     * Retrieve a user by their Querri user ID.
     *
     * @return array<string, mixed>
     */
    public function retrieve(string $userId): array
    {
        return $this->get('/users/' . rawurlencode($userId));
    }

    /**
     * List users with optional filters.
     *
     * @param array{limit?: int, after?: string, external_id?: string}|null $params
     * @return array{data: array<int, array<string, mixed>>, has_more: bool, next_cursor: string|null}
     */
    public function list(?array $params = null): array
    {
        return $this->get('/users', $params);
    }

    /**
     * Update a user.
     *
     * @param array{email?: string, first_name?: string, last_name?: string, role?: string} $params
     * @return array<string, mixed>
     */
    public function update(string $userId, array $params): array
    {
        return $this->patch('/users/' . rawurlencode($userId), $params);
    }

    /**
     * Delete a user.
     *
     * @return array<string, mixed>
     */
    public function del(string $userId): array
    {
        return $this->delete('/users/' . rawurlencode($userId));
    }

    /**
     * Remove an external ID mapping without affecting the user's org membership.
     *
     * @return array<string, mixed>
     */
    public function removeExternalId(string $externalId): array
    {
        return $this->delete('/users/external/' . rawurlencode($externalId));
    }

    /**
     * Get or create a user by external ID (idempotent).
     *
     * This is the key method used by getSession(). It creates the user if not
     * found, or returns the existing user if the external ID already exists.
     *
     * @param array{email?: string, first_name?: string, last_name?: string, role?: string}|null $params
     * @return array<string, mixed>
     */
    public function getOrCreate(string $externalId, ?array $params = null): array
    {
        return $this->put('/users/external/' . rawurlencode($externalId), $params ?? []);
    }
}
