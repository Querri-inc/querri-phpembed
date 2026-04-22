<?php

declare(strict_types=1);

namespace Querri\Embed\Session;

use Querri\Embed\Exceptions\ConfigException;
use Querri\Embed\Exceptions\ConflictException;
use Querri\Embed\Exceptions\QuerriException;
use Querri\Embed\Resources\EmbedResource;
use Querri\Embed\Resources\PoliciesResource;
use Querri\Embed\Resources\UsersResource;

/**
 * High-level convenience that creates an embed session in three steps:
 *
 * 1. User resolution — calls users.getOrCreate() with the external ID.
 * 2. Access policy — if inline sources+filters, auto-creates/reuses a
 *    deterministically-named policy and assigns the user to it.
 *    If policy_ids are provided, those are used directly.
 * 3. Session creation — calls embed.createSession() and returns the token.
 *
 * Takes the specific resources it needs (not the root QuerriClient) so the
 * Session/ subsystem doesn't create a back-edge into its parent package.
 */
final class GetSession
{
    /**
     * @param array{
     *   user: string|array{external_id: string, email?: string, first_name?: string, last_name?: string, role?: string},
     *   access?: array{policy_ids: string[]}|array{sources: string[], filters: array<string, string|string[]>}|null,
     *   origin?: string|null,
     *   ttl?: int,
     * } $params
     */
    public static function execute(
        UsersResource $users,
        PoliciesResource $policies,
        EmbedResource $embed,
        array $params,
    ): GetSessionResult {
        // @phpstan-ignore isset.offset (runtime defense for untyped callers)
        if (!isset($params['user'])) {
            throw new ConfigException(
                "The 'user' parameter is required for getSession().",
            );
        }

        // @phpstan-ignore isset.offset, booleanAnd.alwaysFalse (runtime defense)
        if (is_array($params['user']) && !isset($params['user']['external_id'])) {
            throw new ConfigException(
                "The 'external_id' field is required when 'user' is an array.",
            );
        }

        // --- Step 1: User Resolution ---
        $userResult = self::resolveUser($users, $params);
        $userId = $userResult['id'];
        $externalId = $userResult['external_id'] ?? null;

        // --- Step 2: Access Policy ---
        $access = $params['access'] ?? null;
        if ($access !== null) {
            $policyIds = self::resolveAccess($policies, $access);

            // Atomically replace all policy assignments for this user.
            // This removes any previously assigned policies (e.g., from a prior
            // getSession() call with different filters) and assigns exactly the
            // new set, preventing policy accumulation.
            $policies->replaceUserPolicies($userId, $policyIds);
        }

        // --- Step 3: Create Embed Session ---
        $sessionParams = [
            'user_id' => $userId,
            'ttl' => $params['ttl'] ?? 3600,
        ];

        // Only include origin when explicitly provided (matches JS SDK behavior —
        // JS omits undefined fields; PHP would send "origin": null if we always set it)
        if (isset($params['origin'])) {
            $sessionParams['origin'] = $params['origin'];
        }

        $session = $embed->createSession($sessionParams);

        return new GetSessionResult(
            sessionToken: $session['session_token'],
            expiresIn: $session['expires_in'],
            userId: $userId,
            externalId: $externalId,
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return array{id: string, external_id?: string}
     */
    private static function resolveUser(UsersResource $users, array $params): array
    {
        $user = $params['user'];

        if (is_string($user)) {
            return $users->getOrCreate($user);
        }

        $externalId = $user['external_id'];
        $userData = array_filter([
            'email' => $user['email'] ?? null,
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'role' => $user['role'] ?? null,
        ], fn ($v) => $v !== null);

        return $users->getOrCreate($externalId, $userData ?: null);
    }

    /**
     * @param array<string, mixed> $access
     * @return string[] Policy IDs to assign
     */
    private static function resolveAccess(PoliciesResource $policies, array $access): array
    {
        if (isset($access['policy_ids'])) {
            return $access['policy_ids'];
        }

        // Inline access with sources + filters
        $hash = self::hashAccessSpec($access);
        $policyName = "sdk_auto_{$hash}";

        $existing = self::findPolicyByName($policies, $policyName);

        if ($existing === null) {
            try {
                $existing = $policies->create([
                    'name' => $policyName,
                    'source_ids' => $access['sources'],
                    'row_filters' => self::buildRowFilters($access['filters'] ?? []),
                ]);
            } catch (ConflictException) {
                // TOCTOU race: another request created the policy between our
                // list check and this create call. Re-fetch by name.
                $existing = self::findPolicyByName($policies, $policyName);
                if ($existing === null) {
                    throw new QuerriException(
                        "Failed to create or find policy '{$policyName}'",
                    );
                }
            }
        }

        return [$existing['id']];
    }

    /**
     * Deterministic 8-char hex hash of the access spec.
     *
     * MUST produce the same output as the JS SDK's hashAccessSpec() for identical inputs.
     *
     * Normalization steps:
     *   1. Sort source IDs alphabetically
     *   2. Sort filter keys alphabetically
     *   3. Wrap single filter values in arrays, then sort values
     *   4. JSON encode → SHA-256 → first 8 hex chars
     *
     * The (object) cast on $filters is critical: without it, an empty $filters
     * array encodes as JSON [] instead of {}, breaking hash parity with JS
     * (JSON.stringify({}) → "{}"). For non-empty arrays, the cast is a no-op
     * since PHP preserves string-keyed arrays as objects in JSON.
     *
     * @param array<string, mixed> $access
     */
    private static function hashAccessSpec(array $access): string
    {
        $sources = $access['sources'] ?? [];
        sort($sources);

        $filters = [];
        $filterKeys = array_keys($access['filters'] ?? []);
        sort($filterKeys);

        foreach ($filterKeys as $key) {
            $val = $access['filters'][$key];
            $values = is_array($val) ? $val : [$val];
            sort($values);
            $filters[$key] = $values;
        }

        $normalized = [
            'sources' => $sources,
            'filters' => (object) $filters, // (object) ensures {} not [] for empty filters
        ];

        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return substr(hash('sha256', $json), 0, 8);
    }

    /**
     * Convert inline filters map to row_filters array format.
     *
     * @param array<string, string|string[]> $filters
     * @return array<array{column: string, values: string[]}>
     */
    private static function buildRowFilters(array $filters): array
    {
        $result = [];
        foreach ($filters as $column => $value) {
            $result[] = [
                'column' => $column,
                'values' => is_array($value) ? $value : [$value],
            ];
        }
        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function findPolicyByName(PoliciesResource $policies, string $name): ?array
    {
        $response = $policies->list(['name' => $name]);

        foreach ($response['data'] as $policy) {
            if (($policy['name'] ?? null) === $name) {
                return $policy;
            }
        }

        return null;
    }
}
