# Changelog

All notable changes to `querri/embed` (PHP SDK) are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Prior to `1.0.0`, minor version bumps may contain breaking changes.

## [1.0.1] — 2026-09-28

No code change: `Config::VERSION` (and so the `querri-php/…` user agent) is the
only difference from 1.0.0.

### Fixed

- README: "API key is required" pointed at `app.querri.com/settings/api-keys`,
  which was never a page. API keys are created at `/settings/api`, by an org
  admin.

## [1.0.0] — 2026-08-31

First stable release. Aligns the SDK with the current `/api/v1` server
contract; several 0.2.0 method shapes matched endpoints that no longer
exist and returned 404s.

### Breaking

- **`org_id` is required at construct time.** `Config::resolve()` now throws
  `ConfigException` when no organization ID is passed and `QUERRI_ORG_ID` is
  unset — the API rejects every request without an `X-Tenant-ID` header, so
  a client without one could never make a successful call.
- **`DataResource` repointed from `/data/*` to `/sources/*`.** The server
  merged the standalone data routes into the sources router; the old paths
  404 on current servers. Method names are unchanged except:
  - `query()` is now `query(string $sourceId, array $params)` — the source
    ID moved from the request body to the path
    (`POST /sources/{id}/query`); the body carries `{sql, page?, page_size?}`.
  - **Alias behavior change:** the deprecated `listSources()`/`getSource()`/
    `createSource()`/`deleteSource()` aliases are still present and now
    target the working `/sources/*` endpoints — calls that 404'd on 0.2.0
    now succeed.
- **`SourcesResource::create()` takes `{name, rows}`** (inline JSON rows,
  matching the server's `POST /sources` binding), not
  `{name, connector_id, config}` — the connector-based shape was never
  accepted by this endpoint. `update()` now documents the full server
  shape `{name?, description?, config?, access_controlled?}`.
- **`UserQuerriClient` targets `/api/v1` (was the internal `/api/`)** —
  embed sessions are the highest-priority credential on the public v1 API.
  Its `dashboards` surface is now the read-only `UserDashboardsResource`
  (`list`, `retrieve`, `refreshStatus`): embed sessions lack the
  `admin:dashboards:write` scope server-side, so `create`/`update`/`del`/
  `refresh` could only ever fail at runtime.
- **`BaseResource::delete()` is typed `array<string, mixed>`** (was
  `array{}`): v1 DELETE endpoints return bodies such as `{id, revoked}`.

### Added

- **`default_origin` config option + `QUERRI_EMBED_ORIGIN` env var** — used
  by `getSession()` and `embed->createSession()` when the caller passes no
  origin, so a missing `Origin` header no longer silently mints a session
  without origin binding.
- **Client-side validation guards**: `ttl` outside `[900, 86400]` or an
  `origin` longer than 500 characters throws `ValidationException` before
  any HTTP call (in both `createSession()` and `getSession()`).
- **422 responses now map to `ValidationException`** (was generic
  `ApiException`), keeping the Pydantic-detail message synthesis.
- **Contract surface test + snapshot**
  (`tests/fixtures/openapi.snapshot.json`,
  `tests/Unit/ContractSurfaceTest.php`) asserting every path the SDK builds
  is in the snapshot, and a scheduled weekly workflow
  (`.github/workflows/contract.yml`) checking the snapshot against the live
  OpenAPI schema.
- **Release tag guard**: the release workflow fails when the pushed tag
  doesn't match `Config::VERSION`.

### Fixed

- `SharingResource::orgShareSource()` docblocks now state the actual
  request/response contract (`{enabled, permission}` →
  `{source_id, org_shared}`); the endpoint was restored server-side with an
  unchanged contract.
- Documentation: the session token is an opaque `es_…` string, not a JWT;
  `startView` examples use `/dashboard/…` (the `/builder/…` paths were
  retired); example app chrome uses `{ rail: { show: true } }`; README and
  snippets use `?:` (not `??`) for the `HTTP_ORIGIN` fallback so empty
  headers also fall back to the configured default origin.

See `docs/MIGRATION.md` for before/after snippets.

---

## [0.2.0] — 2026-04-23

### Added

- **`SharingPermission` constants** (`src/Resources/SharingPermission.php`)
  with `VIEW` and `EDIT` string constants. Kept as a string-constant class
  rather than a PHP enum so the wire format stays a plain string — no
  `->value` unwrap at call sites. `SharingResource` docblocks reference
  the constants instead of magic strings.
- **Injectable HTTP transport on `UserQuerriClient`**: third constructor
  argument accepts an `HttpClientInterface|null`, matching `QuerriClient`'s
  existing DI hook. `QuerriClient::asUser()` forwards the transport
  through for end-to-end fixture coverage.
- **`Config::$host`** property exposing the bare origin so consumers
  don't have to regex-derive it from `baseUrl`. `Config::resolve()` and
  `forSession()` strip any `/api` or `/api/v1` suffix from the caller's
  host and store the bare origin.
- **`docs/MIGRATION.md`** documenting the 0.1.x → 0.2.0 upgrade path.
- **Full PHPUnit test suite** on a shared `MockHttpTestCase` base:
  `tests/Unit/Http/HttpClientTest.php` (success, retry, error, auth,
  wire format), `tests/Unit/Session/GetSessionTest.php` (value object
  + 3-step execute flow), `tests/Unit/Exceptions/ApiExceptionTest.php`
  (status dispatch, nested error parsing, FastAPI unwrap), plus per-
  resource smoke tests and leaf-module coverage. 246 tests total.
- **PHPStan level 6** analysis with strict `@return` shape annotations
  across resources; `phpstan.neon` tuned for the codebase.
- **Example `access.filters` block** in
  `examples/react-embed/public/api/querri-session.php` demonstrating
  row-level restriction via column values.

### Changed

- **API method naming aligned with cross-resource CRUD convention.** Old
  names kept as `@deprecated` aliases (scheduled for removal in 0.3.0):
  - `DataResource::listSources` → `list`
  - `DataResource::getSource` → `retrieve`
  - `DataResource::createSource` → `create`
  - `DataResource::deleteSource` → `del`
  - `PoliciesResource::columns` → `listColumns`
  - `PoliciesResource::resolve` → `resolveAccess`
- **Signatures widened** to accept both old and new shapes (old form
  detected at runtime and wrapped, so callers migrate on their own
  schedule):
  - `PoliciesResource::assignUsers` — `{user_ids: [...]}` or bare list
  - `PoliciesResource::replaceUserPolicies` — `{policy_ids: [...]}` or
    bare list
  - `UsageResource::getOrgUsage` / `getUserUsage` — `{period: '...'}`
    or bare string
- **`BaseResource::delete()` `@return`** tightened from
  `array<string, mixed>` to `array{}` — DELETE endpoints return 204 No
  Content. Resource-level `del()` / `revoke()` / `removeX()` methods
  inherit this.
- **`GetSession::execute`** refactored to take `(UsersResource,
  PoliciesResource, EmbedResource)` parameters instead of a whole
  client, making the 3-step user-resolution → policy → session creation
  flow independently testable.
- **Stripe-style error parsing deduplicated** between `ApiException`
  and `RateLimitException`. `ApiException::extractMetadata()` is the
  single entry point; `RateLimitException::fromResponse` delegates and
  only adds the `Retry-After` parse.
- **PHPStan upgraded** from 1.12 to 2.x; `.github/workflows/ci.yml`
  updated accordingly.
- **Demo data in the react-embed example** (`public/api/querri-session.php`,
  `public/api/user-projects.php`) replaced with generic placeholders
  (`demo-user-123`, `demo.user@example.com`, `your_source_name_or_uuid`,
  example `tenant_id` filter) so the example is safe to ship in a
  public repo.

### Deprecated

All of the following remain functional and emit no runtime warning; they
will be removed in 0.3.0. See `docs/MIGRATION.md` for the migration path.

- `DataResource::listSources`, `getSource`, `createSource`,
  `deleteSource`
- `PoliciesResource::columns`, `resolve`
- Bare-argument signatures on `PoliciesResource::assignUsers`,
  `replaceUserPolicies`, `UsageResource::getOrgUsage`, `getUserUsage`
- `GetSessionResult::toArray` — one-line pass-through to
  `jsonSerialize()`; callers should use `jsonSerialize()` directly.

### Fixed

- **FastAPI `{detail: ...}` envelope unwrap** in
  `ApiException::extractMetadata()` (`src/Exceptions/ApiException.php`).
  The Querri backend (FastAPI) wraps errors in three shapes:
  `{detail: {error: {...}}}` (HTTPException around a Stripe-shaped
  error), `{detail: [{type, loc, msg, ...}]}` (Pydantic validation),
  and `{detail: "string"}`. The parser previously only handled flat
  `body['error']` / `body['message']`, so every FastAPI error came
  through as `"Request failed with status {status}"` with `code: null`,
  dropping the message, type, code, and `doc_url` on the floor.
  Normalized once via `unwrapFastApiDetail()` before the existing
  parser. `$exception->body` continues to hold the original wrapped
  body for debugging.
- **`HttpClient` header handling** for the scalar-vs-array distinction
  — bug found by new leaf-module tests. Symfony returns `string[]` for
  some headers and plain `string` for others; the reader now copes with
  both.

---

## Versions prior to 0.2.0

Earlier releases (v0.1.0 through v0.1.5) are documented via git tag
history. Run `git tag -l 'v0.1.*'` and inspect the tagged commits for
details.
