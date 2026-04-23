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
     *
     * @return array<string, mixed>
     */
    public function refreshSession(string $sessionToken): array
    {
        return $this->post('/embed/sessions/refresh', [
            'session_token' => $sessionToken,
        ]);
    }

    /**
     * List active embed sessions.
     *
     * @param array{limit?: int, after?: string}|null $params
     * @return array{data: array<int, array<string, mixed>>, has_more: bool, next_cursor: string|null}
     */
    public function listSessions(?array $params = null): array
    {
        return $this->get('/embed/sessions', array_merge(
            ['limit' => 100],
            $params ?? [],
        ));
    }

    /**
     * Revoke an embed session.
     *
     * @return array<string, mixed>
     */
    public function revokeSession(string $sessionId): array
    {
        return $this->delete('/embed/sessions/' . rawurlencode($sessionId));
    }

    /**
     * Revoke all embed sessions for a given user ID.
     *
     * Note: The embed sessions endpoint uses Redis SCAN and always returns
     * has_more=false, so a single request fetches all available sessions.
     *
     * @return int Number of sessions revoked
     */
    public function revokeUserSessions(string $userId): int
    {
        $sessions = $this->listSessions();
        $revoked = 0;

        foreach ($sessions['data'] as $session) {
            if (($session['user_id'] ?? null) === $userId) {
                $this->revokeSession($session['session_token']);
                $revoked++;
            }
        }

        return $revoked;
    }
}
