<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

use Querri\Embed\Exceptions\ValidationException;

/**
 * Embed sessions API — create, refresh, list, and revoke embed session tokens.
 */
final class EmbedResource extends BaseResource
{
    public const MIN_TTL = 900;
    public const MAX_TTL = 86400;
    public const MAX_ORIGIN_LENGTH = 500;

    /**
     * Create an embed session token.
     *
     * When no origin is passed, the config's default origin (constructor
     * option `default_origin` / env `QUERRI_EMBED_ORIGIN`) is used instead.
     *
     * @param array{user_id: string, origin?: string|null, ttl?: int} $params
     *   ttl: seconds, 900–86400 (default 3600)
     * @return array{session_token: string, expires_in: int, user_id: string|null}
     *
     * @throws ValidationException when ttl or origin fails client-side validation
     */
    public function createSession(array $params): array
    {
        $ttl = $params['ttl'] ?? 3600;
        self::assertValidTtl($ttl);

        $origin = $params['origin'] ?? $this->client->config->defaultOrigin;
        self::assertValidOrigin($origin);

        $body = [
            'user_id' => $params['user_id'],
            'ttl' => $ttl,
        ];

        if ($origin !== null) {
            $body['origin'] = $origin;
        }

        return $this->post('/embed/sessions', $body);
    }

    /**
     * Client-side guard: reject a ttl the server would refuse or clamp.
     *
     * @throws ValidationException
     */
    public static function assertValidTtl(int $ttl): void
    {
        if ($ttl < self::MIN_TTL || $ttl > self::MAX_TTL) {
            throw new ValidationException(
                'ttl must be between ' . self::MIN_TTL . ' and ' . self::MAX_TTL
                . " seconds, got {$ttl}.",
                status: 400,
            );
        }
    }

    /**
     * Client-side guard: reject an origin longer than the server accepts.
     *
     * @throws ValidationException
     */
    public static function assertValidOrigin(?string $origin): void
    {
        if ($origin !== null && strlen($origin) > self::MAX_ORIGIN_LENGTH) {
            throw new ValidationException(
                'origin must be at most ' . self::MAX_ORIGIN_LENGTH
                . ' characters, got ' . strlen($origin) . '.',
                status: 400,
            );
        }
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
