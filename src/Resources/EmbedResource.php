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
    public const MAX_ROLE_LENGTH = 100;

    /**
     * Create an embed session token.
     *
     * When no origin is passed, the config's default origin (constructor
     * option `default_origin` / env `QUERRI_EMBED_ORIGIN`) is used instead.
     *
     * `role` (Custom Roles) is the role id or key the session runs as. It is
     * a different thing from a user's org role (`admin`/`member`, the `role`
     * field of UsersResource::getOrCreate()). A role the user already holds is
     * selected; a role they do not hold is granted only when the key has the
     * `embed:role:assign` scope and the role is on the organization's embed
     * allow-list. The response carries `role_id` only when a role was applied.
     * Decide `role` on your server from your own auth — never take it from the
     * browser's request.
     *
     * @param array{user_id: string, origin?: string|null, ttl?: int, role?: string|null} $params
     *   ttl: seconds, 900–86400 (default 3600); role: at most 100 characters
     * @return array{session_token: string, expires_in: int, user_id: string|null, role_id?: string}
     *
     * @throws ValidationException when ttl, origin or role fails client-side validation
     * @throws \Querri\Embed\Exceptions\NotFoundException `role_not_found` for an unknown role
     * @throws \Querri\Embed\Exceptions\PermissionException `role_not_allowed`, `role_disabled`,
     *   `insufficient_scope` or `seat_change_required` when the role cannot be applied
     */
    public function createSession(array $params): array
    {
        $ttl = $params['ttl'] ?? 3600;
        self::assertValidTtl($ttl);

        $origin = $params['origin'] ?? $this->client->config->defaultOrigin;
        self::assertValidOrigin($origin);

        $role = $params['role'] ?? null;
        self::assertValidRole($role);

        $body = [
            'user_id' => $params['user_id'],
            'ttl' => $ttl,
        ];

        if ($origin !== null) {
            $body['origin'] = $origin;
        }

        if ($role !== null) {
            $body['role'] = $role;
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
     * Client-side guard: reject a role reference longer than the server accepts.
     *
     * @throws ValidationException
     */
    public static function assertValidRole(?string $role): void
    {
        if ($role !== null && strlen($role) > self::MAX_ROLE_LENGTH) {
            throw new ValidationException(
                'role must be at most ' . self::MAX_ROLE_LENGTH
                . ' characters, got ' . strlen($role) . '.',
                status: 400,
            );
        }
    }

    /**
     * Refresh an embed session token. The new token keeps the old session's
     * role, and the response echoes it as `role_id` when the session has one.
     *
     * @return array{session_token: string, expires_in: int, user_id: string|null, role_id?: string}
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
     * Each item is `{session_token, user_id, origin, created_at, auth_method, role_id: string|null}`;
     * `role_id` is the role the session runs as, or null.
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
