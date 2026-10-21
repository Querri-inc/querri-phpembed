<?php

declare(strict_types=1);

namespace Querri\Embed\Exceptions;

/**
 * Documented `/v1` error codes, as found on {@see ApiException::$errorCode}.
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
     * roles embeds may grant (Settings → Embed), or the key's creator does not
     * hold every permission in it.
     */
    public const ROLE_NOT_ALLOWED = 'role_not_allowed';
    /** 403 — the role is a draft; an admin must enable it first. */
    public const ROLE_DISABLED = 'role_disabled';
    /** 403 — the role needs a creator seat; an admin must assign it in Settings. */
    public const SEAT_CHANGE_REQUIRED = 'seat_change_required';

    // --- Users / sessions ---
    /** 403 — the user is not a member of the organization. */
    public const NOT_ORG_MEMBER = 'not_org_member';
    /** 404 — no user with that id. */
    public const USER_NOT_FOUND = 'user_not_found';
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

    // --- `reason` values on a PERMISSION_DENIED error ---
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
    /** Denied for another reason. */
    public const REASON_FORBIDDEN = 'forbidden';

    private function __construct()
    {
    }
}
