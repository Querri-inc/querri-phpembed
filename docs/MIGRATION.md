# Migration guide

## 0.2.x → 1.0.0

1.0.0 aligns the SDK with the current `/api/v1` server contract. Several
0.2.0 shapes addressed endpoints that no longer exist — those calls 404'd,
so if your code worked on 0.2.0 it most likely needs only the `org_id`
change.

### `org_id` is required at construct time

The API rejects every request without an `X-Tenant-ID` header, so the SDK
now refuses to construct without an organization ID.

```php
// BEFORE (0.2.0) — constructed fine, then every call 400'd
$client = new QuerriClient('qk_...');

// AFTER (1.0.0) — pass org_id, or set QUERRI_ORG_ID in the environment
$client = new QuerriClient(['api_key' => 'qk_...', 'org_id' => 'org_...']);
$client = new QuerriClient('qk_...'); // still fine IF QUERRI_ORG_ID is set
```

`Config::resolve()` now throws `ConfigException` when neither is provided.

### `DataResource` paths moved from `/data/*` to `/sources/*`

Every `data->…` call on 0.2.0 hit `/data/sources…`, which 404s on current
servers. Method names are unchanged; calls that failed now work. One
signature changed — `query()` takes the source ID as its first argument:

```php
// BEFORE (0.2.0) — POST /data/query with source_id in the body (404'd)
$client->data->query([
    'sql' => 'SELECT 1',
    'source_id' => 'src_abc',
]);

// AFTER (1.0.0) — POST /sources/{id}/query
$client->data->query('src_abc', [
    'sql' => 'SELECT 1',
    'page' => 1,          // optional
    'page_size' => 100,   // optional
]);
```

The deprecated aliases (`listSources`, `getSource`, `createSource`,
`deleteSource`) remain and now target the working endpoints.

### `sources->create()` takes `{name, rows}`

The server binds `POST /sources` to inline-rows creation; the 0.2.0
connector shape was never accepted by this endpoint.

```php
// BEFORE (0.2.0) — 422'd
$client->sources->create([
    'name' => 'Production DB',
    'connector_id' => 'conn_postgres',
    'config' => ['host' => '...'],
]);

// AFTER (1.0.0)
$client->sources->create([
    'name' => 'Sales Data',
    'rows' => [['region' => 'US', 'revenue' => 1000]],
]);
```

### `UserQuerriClient` loses dashboard writes

Embed sessions are read-only for dashboards (the server excludes the
`admin:dashboards:write` scope from embed auth). The user-scoped
`dashboards` property is now a `UserDashboardsResource` with `list()`,
`retrieve()`, and `refreshStatus()` only.

```php
// BEFORE (0.2.0) — compiled, then 403'd/404'd at runtime
$userClient->dashboards->refresh($id);
$userClient->dashboards->update($id, ['name' => 'x']);

// AFTER (1.0.0) — write methods don't exist; use the admin client
$client->dashboards->refresh($id);        // admin QuerriClient
$userClient->dashboards->list();          // reads still work, FGA-filtered
```

`UserQuerriClient` also now targets `/api/v1` (was the internal `/api/`),
which is transparent unless you inspected URLs.

### ttl / origin validation happens client-side

`ttl` outside `[900, 86400]` or an `origin` over 500 characters throws
`ValidationException` from `getSession()` / `createSession()` before any
HTTP call. 0.2.0 sent the request and let the server clamp or reject.

```php
// BEFORE (0.2.0) — server silently clamped ttl to 900
$client->getSession(['user' => 'u', 'ttl' => 60]);

// AFTER (1.0.0) — throws ValidationException locally; pass 900–86400
$client->getSession(['user' => 'u', 'ttl' => 900]);
```

### New: default origin

Set `default_origin` in the config (or `QUERRI_EMBED_ORIGIN` in the
environment) and it is used whenever `getSession()` / `createSession()` is
called without an explicit origin — including when
`$_SERVER['HTTP_ORIGIN']` is absent or empty. Prefer `?:` over `??` when
forwarding the header so empty strings also fall back:

```php
'origin' => ($_SERVER['HTTP_ORIGIN'] ?? '') ?: null,  // null → default_origin
```

### Smaller notes

- 422 responses now raise `ValidationException` (was generic `ApiException`).
- `BaseResource::delete()` is typed `array<string, mixed>`; v1 DELETEs
  return bodies such as `{id, revoked}`.
- `sources->update()` accepts `{name?, description?, config?, access_controlled?}`.
- The session token is an opaque `es_…` string — never decode it as a JWT.
- Pair with `@querri-inc/embed` `^1.0.0` on the frontend.

---

## 0.1.x → 0.2.0

No code must change to upgrade — every rename and signature adjustment keeps
the old form working as a `@deprecated` path. The deprecated paths will be
removed in 0.3.0. Recommended: update to the new names now so future upgrades
are friction-free.

### Renamed methods

| Since 0.2.0 (preferred) | 0.1.x (deprecated, removed in 0.3.0) |
|---|---|
| `$client->data->list()` | `$client->data->listSources()` |
| `$client->data->retrieve($id)` | `$client->data->getSource($id)` |
| `$client->data->create($params)` | `$client->data->createSource($params)` |
| `$client->data->del($id)` | `$client->data->deleteSource($id)` |
| `$client->policies->listColumns($sourceId)` | `$client->policies->columns($sourceId)` |
| `$client->policies->resolveAccess($userId, $sourceId)` | `$client->policies->resolve($userId, $sourceId)` |

### Signature changes (old form still accepted at runtime)

Previously pass a bare list or string; now pass a shape. The old form is
detected and wrapped.

```php
// NEW (preferred)
$client->policies->assignUsers('pol_abc', ['user_ids' => ['u_1', 'u_2']]);
$client->policies->replaceUserPolicies('user_1', ['policy_ids' => ['pol_1']]);
$client->usage->getOrgUsage(['period' => 'last_30_days']);
$client->usage->getUserUsage('u_1', ['period' => 'last_month']);

// OLD (still works in 0.2.0, removed in 0.3.0)
$client->policies->assignUsers('pol_abc', ['u_1', 'u_2']);
$client->policies->replaceUserPolicies('user_1', ['pol_1']);
$client->usage->getOrgUsage('last_30_days');
$client->usage->getUserUsage('u_1', 'last_month');
```

### `GetSessionResult::toArray()`

`toArray()` is now `@deprecated`. It's a one-line pass-through to
`jsonSerialize()`. Prefer calling `jsonSerialize()` directly, or just pass
the object to `json_encode()` — `JsonSerializable` handles the conversion.

### Internal-only changes (not user-facing, listed for completeness)

- `Config` now exposes a bare `host` property alongside `baseUrl`. Custom
  integrations that reverse-derived a host from `baseUrl` can use
  `$config->host` instead.
- `GetSession::execute()` now takes `UsersResource`, `PoliciesResource`, and
  `EmbedResource` directly instead of the root `QuerriClient`. The
  `QuerriClient::getSession()` helper is unchanged; only direct callers of
  `GetSession::execute()` (which is internal) need to update.
- `UserQuerriClient::__construct()` and `QuerriClient::asUser()` both gained
  an optional `?HttpClientInterface $httpClient` parameter for test
  injection. Existing two-argument calls continue to work.
