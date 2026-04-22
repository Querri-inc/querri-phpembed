# Migration guide

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
