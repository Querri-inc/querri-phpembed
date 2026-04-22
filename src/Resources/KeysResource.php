<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * API Keys management — create, list, retrieve, and revoke API keys.
 */
final class KeysResource extends BaseResource
{
    /**
     * @param array{name: string, scopes: string[], source_scope?: array<string, mixed>, access_policy_ids?: string[], bound_user_id?: string, rate_limit_per_minute?: int, expires_in_days?: int, ip_allowlist?: string[]} $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->post('/keys', $params);
    }

    /**
     * @param array{limit?: int, after?: string}|null $params
     * @return array{data: array<int, array<string, mixed>>, has_more: bool, next_cursor: string|null}
     */
    public function list(?array $params = null): array
    {
        return $this->get('/keys', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieve(string $keyId): array
    {
        return $this->get('/keys/' . rawurlencode($keyId));
    }

    /**
     * @return array<string, mixed>
     */
    public function revoke(string $keyId): array
    {
        return $this->delete('/keys/' . rawurlencode($keyId));
    }
}
