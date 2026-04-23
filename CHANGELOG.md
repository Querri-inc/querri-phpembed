# Changelog

All notable changes to `querri/embed` (PHP SDK) are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Prior to `1.0.0`, minor version bumps may contain breaking changes.

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
