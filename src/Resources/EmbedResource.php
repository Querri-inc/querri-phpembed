<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Embed sessions API — create, refresh, list, and revoke embed session tokens.
 */
final class EmbedResource extends BaseResource
{
    /**
     * Create an embed session token.
     *
     * @param array{user_id: string, origin?: string|null, ttl?: int} $params
     * @return array{session_token: string, expires_in: int, user_id: string|null}
     */
    public function createSession(array $params): array
    {
        $body = [
            'user_id' => $params['user_id'],
            'ttl' => $params['ttl'] ?? 3600,
        ];

        if (isset($params['origin'])) {
            $body['origin'] = $params['origin'];
        }

        return $this->post('/embed/sessions', $body);
    }

    /**
     * Refresh an embed session token.
     */
    public function refreshSession(string $sessionToken): array
    {
        return $this->post('/embed/sessions/refresh', [
            'session_token' => $sessionToken,
        ]);
    }

    /**
     * List active embed sessions.
     */
    public function listSessions(?int $limit = null): array
    {
        return $this->get('/embed/sessions', [
            'limit' => $limit ?? 100,
        ]);
    }

    /**
     * Revoke an embed session.
     */
    public function revokeSession(string $sessionId): array
    {
        return $this->delete('/embed/sessions/' . rawurlencode($sessionId));
    }
}
