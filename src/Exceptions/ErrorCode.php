<?php

declare(strict_types=1);

namespace Querri\Embed\Exceptions;

/**
 * The `/v1` error codes for authentication, access, Custom Roles and embed
 * sessions, as found on {@see ApiException::$errorCode}. The API sends other
 * codes too, so keep a default branch.
 *
 * Use these instead of string literals when branching on a failure:
 *
 *     catch (PermissionException $e) {
 *         if ($e->errorCode === ErrorCode::ROLE_DISABLED) { ... }
 *     }
 *
 * The exception class still follows the HTTP status (401 → AuthenticationException,
 * 403 → PermissionException, 404 → NotFoundException, …); the code says why.
 * The server may add codes over time, so always keep a default branch.
 */
final class ErrorCode
{
    // --- Authentication (401) ---
    /** 401 — no `Authorization: Bearer qk_…` key or JWT was sent. */
    public const MISSING_API_KEY = 'missing_api_key';
    /** 401 — the API key is invalid, expired, or revoked. */
    public const INVALID_API_KEY = 'invalid_api_key';
    /** 400 — the request has no `X-Tenant-ID` organization header. */
    public const MISSING_TENANT_ID = 'missing_tenant_id';
    /** 401 — the JWT is invalid or expired. */
    public const INVALID_JWT = 'invalid_jwt';
    /** 401 — the `X-Embed-Session` token is invalid, expired, or revoked. */
    public const INVALID_EMBED_SESSION = 'invalid_embed_session';

    // --- Access (403 / 429) ---
    /** 403 — the caller's IP is not on the key's allow-list. */
    public const IP_NOT_ALLOWED = 'ip_not_allowed';
    /** 403 — the organization is scheduled for deletion. */
    public const ORG_SCHEDULED_FOR_DELETION = 'org_scheduled_for_deletion';
    /** 429 — too many requests; see RateLimitException::$retryAfter. */
    public const RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    /**
     * 403 — the API key lacks the scope this call needs. On session creation it
     * means the user does not hold the requested `role` and the key has no
     * `embed:role:assign` scope to grant it.
     */
    public const INSUFFICIENT_SCOPE = 'insufficient_scope';
    /**
     * 403 — the caller's role does not grant the action. The exception's
     * `permission` names the permission key and `reason` says why.
     */
    public const PERMISSION_DENIED = 'permission_denied';

    // --- Embed session roles (POST /v1/embed/sessions with `role`) ---
    /** 404 — no role with that id or key exists in the organization. */
    public const ROLE_NOT_FOUND = 'role_not_found';
    /**
     * 403 — the role is the Admin role, is not on the organization's list of
     * roles embeds may grant (Settings → Embed), or the people behind the key
     * don't hold every permission in it: on servers released after 2026-10-21,
     * both its creator and its bound user (if any) must hold them; earlier
     * servers check its bound user, else its creator.
     */
    public const ROLE_NOT_ALLOWED = 'role_not_allowed';
    /** 403 — the role is a draft; an admin must enable it first. */
    public const ROLE_DISABLED = 'role_disabled';
    /** 403 — the role needs a creator seat; an admin must assign it in Settings. */
    public const SEAT_CHANGE_REQUIRED = 'seat_change_required';

    // --- Users / sessions ---
    /** 403 — the user is not a member of the organization. */
    public const NOT_ORG_MEMBER = 'not_org_member';
    /**
     * No user with that id: 400 on embed session creation (a ValidationException
     * there), 404 on the users endpoints.
     */
    public const USER_NOT_FOUND = 'user_not_found';
    /**
     * 403 — the change would give or remove permissions the caller doesn't
     * hold (with an API key: its creator and its bound user, if any) — on `/v1`
     * users create, update, del and getOrCreate, so also getSession() for a
     * new user. The refused permission keys are only in the raw body:
     * `$e->body['detail']['error']['missing']`.
     */
    public const ESCALATION_DENIED = 'escalation_denied';
    /**
     * 409 (a ConflictException) — the change would leave the organization
     * without an admin.
     */
    public const LAST_ADMIN = 'last_admin';
    /**
     * 400 — a parameter was refused, e.g. a key minted by a bound key bound to
     * a different user, or a `bound_user_id` that is not a real user.
     */
    public const INVALID_PARAMETERS = 'invalid_parameters';
    /** 400 — the organization has a domain allow-list, so `origin` is required. */
    public const ORIGIN_REQUIRED = 'origin_required';
    /** 403 — the `origin` is not on the organization's allowed domains. */
    public const ORIGIN_NOT_ALLOWED = 'origin_not_allowed';
    /** 404 — the embed session does not exist or has expired. */
    public const SESSION_NOT_FOUND = 'session_not_found';
    /** 403 — the embed session belongs to another organization. */
    public const ORG_MISMATCH = 'org_mismatch';
    /** 500 — the embed session could not be created. */
    public const SESSION_CREATION_FAILED = 'session_creation_failed';
    /** 500 — the embed session could not be refreshed. */
    public const REFRESH_FAILED = 'refresh_failed';
    /** 500 — the organization membership check failed. */
    public const MEMBERSHIP_CHECK_FAILED = 'membership_check_failed';

    // --- `reason` values on a PERMISSION_DENIED error (the server may add more) ---
    /** The caller's role does not include the permission. */
    public const REASON_ROLE_DENIED = 'role_denied';
    /** The organization's plan does not include the feature. */
    public const REASON_NOT_IN_PLAN = 'not_in_plan';
    /** The feature is turned off for the organization. */
    public const REASON_FLAG_OFF = 'flag_off';
    /** The action is not available to embed sessions. */
    public const REASON_EMBED_DISABLED = 'embed_disabled';
    /** A permission this one depends on is not granted. */
    public const REASON_DEPENDENCY_UNMET = 'dependency_unmet';
    /**
     * Creating an API key with scopes its creator or bound user lacks. The
     * refused scopes are only in the raw body:
     * `$e->body['detail']['error']['scopes']`.
     */
    public const REASON_SCOPE_EXCEEDS_ROLE = 'scope_exceeds_role';
    /** Denied for another reason. */
    public const REASON_FORBIDDEN = 'forbidden';

    private function __construct()
    {
    }
}
