<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * API Keys management — create, list, retrieve, and revoke API keys.
 */
final class KeysResource extends BaseResource
{
    /**
     * @param array{name: string, scopes: string[], source_scope?: array, access_policy_ids?: string[], bound_user_id?: string, rate_limit_per_minute?: int, expires_in_days?: int, ip_allowlist?: string[]} $params
     */
    public function create(array $params): array
    {
        return $this->post('/keys', $params);
    }

    public function list(): array
    {
        return $this->get('/keys');
    }

    public function retrieve(string $keyId): array
    {
        return $this->get('/keys/' . rawurlencode($keyId));
    }

    public function revoke(string $keyId): array
    {
        return $this->delete('/keys/' . rawurlencode($keyId));
    }
}
