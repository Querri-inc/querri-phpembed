# Querri PHP Server SDK Reference

The Querri PHP SDK (`querri/embed`) provides a server-side client for the Querri API, covering user management, embed session creation, and access policies. For most apps, a single `getSession()` call is all you need to create embed sessions.

```bash
composer require querri/embed
```

## Quick Start

### Plain PHP

```php
// public/api/querri-session.php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Querri\Embed\QuerriClient;
use Querri\Embed\Exceptions\ApiException;
use Querri\Embed\Exceptions\QuerriException;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

try {
    $client = new QuerriClient();  // reads QUERRI_API_KEY from env

    // In production, derive user identity from YOUR auth system.
    // Never read user/access from the request body — a malicious client
    // can impersonate any user or escalate access.
    $authUser = getAuthenticatedUser();  // your auth logic

    $session = $client->getSession([
        'user' => [
            'external_id' => $authUser->id,
            'email' => $authUser->email,
        ],
        'access' => [
            'sources' => ['src_sales_data'],
            'filters' => ['tenant_id' => $authUser->tenantId],
        ],
        'ttl' => 3600,
    ]);

    echo json_encode($session);
} catch (ApiException $e) {
    http_response_code($e->status >= 400 ? $e->status : 500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (QuerriException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

### Laravel

```php
// routes/api.php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Querri\Embed\QuerriClient;

Route::post('/querri-session', function (Request $request) {
    $client = new QuerriClient();
    $user = $request->user();

    $session = $client->getSession([
        'user' => [
            'external_id' => (string) $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
        ],
        'access' => [
            'sources' => ['src_sales_data'],
            'filters' => ['tenant_id' => $user->tenant_id],
        ],
    ]);

    return response()->json($session);
})->middleware('auth');
```

### Symfony

```php
// src/Controller/QuerriSessionController.php
namespace App\Controller;

use Querri\Embed\QuerriClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class QuerriSessionController extends AbstractController
{
    #[Route('/api/querri-session', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function __invoke(): JsonResponse
    {
        $client = new QuerriClient();
        $user = $this->getUser();

        $session = $client->getSession([
            'user' => [
                'external_id' => $user->getUserIdentifier(),
                'email' => $user->getEmail(),
            ],
            'access' => [
                'sources' => ['src_sales_data'],
                'filters' => ['tenant_id' => $user->getTenantId()],
            ],
        ]);

        return $this->json($session);
    }
}
```

### Understanding `filters`

The `filters` field in inline access uses column names as keys and allowed values:

```php
'access' => [
    'sources' => ['src_sales'],
    'filters' => [
        'tenant_id' => 'acme',                // exact match
        'region'    => ['us-east', 'us-west'], // any of these values (OR)
    ],
],
```

The SDK auto-creates and caches a named access policy from this specification.

> **Security:** Always derive user identity and access from your server-side auth system. Never read `user` or `access` from the request body — a malicious client can impersonate any user or escalate access.

---

## Table of Contents

- [Configuration](#configuration)
- [Resource API Reference](#resource-api-reference)
  - [Users](#users)
  - [Embed](#embed)
  - [Policies](#policies)
- [getSession() Deep Dive](#getsession-deep-dive)
- [Error Handling](#error-handling)
- [Framework Guides](#framework-guides)
  - [Plain PHP](#plain-php-1)
  - [Laravel](#laravel-1)
  - [Symfony](#symfony-1)
  - [React + PHP (Vite)](#react--php-vite)

---

## Configuration

### Constructor

You can pass a full config array, a plain API key string, a `Config` object, or nothing (env-only):

```php
use Querri\Embed\QuerriClient;
use Querri\Embed\Config;

// Read from environment variables
$client = new QuerriClient();

// API key string shorthand
$client = new QuerriClient('qk_your_api_key');

// Full config array
$client = new QuerriClient([
    'api_key'     => 'qk_your_api_key',
    'org_id'      => 'org_123',
    'host'        => 'https://app.querri.com',
    'timeout'     => 30.0,
    'max_retries' => 3,
]);

// Config object
$client = new QuerriClient(Config::resolve(
    apiKey: 'qk_your_api_key',
    orgId: 'org_123',
));
```

Both `camelCase` and `snake_case` keys are accepted in the config array (`apiKey`/`api_key`, `orgId`/`org_id`, `maxRetries`/`max_retries`).

### Environment Variables

The client reads these environment variables as fallbacks when values are not provided in the config:

| Variable | Maps to | Description |
|---|---|---|
| `QUERRI_API_KEY` | `api_key` | API key for authentication |
| `QUERRI_ORG_ID` | `org_id` | Organization / tenant ID |
| `QUERRI_URL` | `host` | API host URL |

Resolution order: explicit config value > `getenv()` > `$_ENV` > `$_SERVER` > default value.

### Config Parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `api_key` | `string` | _(required)_ | Your Querri API key (`qk_...`) |
| `org_id` | `string\|null` | `null` | Organization ID. Sent as `X-Tenant-ID` header. |
| `host` | `string` | `https://app.querri.com` | API host. `/api/v1` is appended automatically. |
| `timeout` | `float` | `30.0` | Request timeout in seconds |
| `max_retries` | `int` | `3` | Max retry attempts on 429/5xx errors |

---

## Resource API Reference

### Users

Manage users in your organization.

#### `$client->users->create($params)`

Create a new user.

```php
create(array $params): array
```

```php
$user = $client->users->create([
    'email' => 'alice@example.com',
    'external_id' => 'usr_alice',
    'first_name' => 'Alice',
    'last_name' => 'Smith',
    'role' => 'viewer',
]);
```

#### `$client->users->retrieve($userId)`

Fetch a single user by their Querri user ID.

```php
retrieve(string $userId): array
```

```php
$user = $client->users->retrieve('user_abc123');
```

#### `$client->users->list($params)`

List users with optional filters.

```php
list(?array $params = null): array
```

```php
$users = $client->users->list(['limit' => 50]);
$users = $client->users->list(['external_id' => 'usr_alice']);
```

#### `$client->users->update($userId, $params)`

Update an existing user.

```php
update(string $userId, array $params): array
```

```php
$updated = $client->users->update('user_abc123', ['role' => 'admin']);
```

#### `$client->users->del($userId)`

Delete a user.

```php
del(string $userId): array
```

```php
$result = $client->users->del('user_abc123');
// ['id' => 'user_abc123', 'deleted' => true]
```

#### `$client->users->getOrCreate($externalId, $params)`

Look up a user by external ID, creating them if they do not exist. This is an upsert operation backed by `PUT /users/external/:externalId`.

```php
getOrCreate(string $externalId, ?array $params = null): array
```

```php
$user = $client->users->getOrCreate('usr_alice', [
    'email' => 'alice@example.com',
    'first_name' => 'Alice',
]);
```

---

### Embed

Manage embed session tokens.

#### `$client->embed->createSession($params)`

Create a new embed session token for a user.

```php
createSession(array $params): array
```

```php
$session = $client->embed->createSession([
    'user_id' => 'user_abc123',
    'origin' => 'https://myapp.com',
    'ttl' => 7200,
]);
// ['session_token' => '...', 'expires_in' => 7200, 'user_id' => 'user_abc123']
```

#### `$client->embed->refreshSession($sessionToken)`

Refresh an existing session token, extending its lifetime.

```php
refreshSession(string $sessionToken): array
```

```php
$refreshed = $client->embed->refreshSession('sess_token_...');
```

#### `$client->embed->listSessions($limit)`

List active embed sessions.

```php
listSessions(?int $limit = null): array
```

```php
$sessions = $client->embed->listSessions(50);
// ['data' => [...], 'count' => 12]
```

#### `$client->embed->revokeSession($sessionId)`

Revoke (invalidate) an embed session.

```php
revokeSession(string $sessionId): array
```

```php
$result = $client->embed->revokeSession('sess_abc');
// ['session_id' => 'sess_abc', 'revoked' => true]
```

---

### Policies

Manage access policies that control which data sources and rows users can see.

#### `$client->policies->create($params)`

Create a new access policy.

```php
create(array $params): array
```

```php
$policy = $client->policies->create([
    'name' => 'acme-corp-policy',
    'source_ids' => ['src_1', 'src_2'],
    'row_filters' => [
        ['column' => 'tenant_id', 'values' => ['acme']],
    ],
]);
```

#### `$client->policies->retrieve($policyId)`

Fetch a single policy by ID.

```php
retrieve(string $policyId): array
```

```php
$policy = $client->policies->retrieve('pol_abc');
```

#### `$client->policies->list($params)`

List all policies. Optionally filter by name.

```php
list(?array $params = null): array
```

```php
$policies = $client->policies->list();
$specific = $client->policies->list(['name' => 'acme-corp-policy']);
```

#### `$client->policies->update($policyId, $params)`

Update an existing policy.

```php
update(string $policyId, array $params): array
```

```php
$client->policies->update('pol_abc', [
    'row_filters' => [
        ['column' => 'tenant_id', 'values' => ['acme', 'globex']],
    ],
]);
```

#### `$client->policies->del($policyId)`

Delete a policy.

```php
del(string $policyId): array
```

```php
$client->policies->del('pol_abc');
```

#### `$client->policies->assignUsers($policyId, $userIds)`

Assign one or more users to a policy.

```php
assignUsers(string $policyId, array $userIds): array
```

```php
$client->policies->assignUsers('pol_abc', ['user_1', 'user_2']);
```

#### `$client->policies->removeUser($policyId, $userId)`

Remove a single user from a policy.

```php
removeUser(string $policyId, string $userId): array
```

```php
$client->policies->removeUser('pol_abc', 'user_1');
```

#### `$client->policies->resolve($userId, $sourceId)`

Resolve the effective access for a user on a specific data source, taking all assigned policies into account.

```php
resolve(string $userId, string $sourceId): array
```

```php
$access = $client->policies->resolve('user_1', 'src_1');
// ['user_id' => ..., 'source_id' => ..., 'resolved_filters' => ..., 'where_clause' => ...]
```

#### `$client->policies->columns($sourceId)`

List available columns for filtering. Optionally scoped to a single source.

```php
columns(?string $sourceId = null): array
```

```php
$cols = $client->policies->columns('src_1');
// [['source_id' => ..., 'source_name' => ..., 'columns' => [['name' => ..., 'type' => ...]]]]
```

---

## getSession() Deep Dive

`$client->getSession()` is the flagship convenience method for the embed use case. It orchestrates three steps in a single call: user resolution, access policy setup, and embed session creation.

### The 3-Step Flow

```
1. User Resolution      -->  users->getOrCreate()
2. Access Policy Setup   -->  policies->create() + policies->assignUsers()
3. Session Creation      -->  embed->createSession()
```

### Basic Usage

```php
$session = $client->getSession([
    'user' => 'usr_alice',
    'access' => [
        'sources' => ['src_sales'],
        'filters' => ['tenant_id' => 'acme'],
    ],
    'ttl' => 3600,
    'origin' => 'https://myapp.com',
]);

$session->sessionToken;  // string — JWT for the embed
$session->expiresIn;     // int — seconds until expiry
$session->userId;        // string — Querri user ID
$session->externalId;    // string|null — your external ID
```

### Parameters

```php
$client->getSession(array $params): GetSessionResult
```

| Parameter | Type | Required | Description |
|---|---|---|---|
| `user` | `string\|array` | Yes | External ID string, or array with `external_id` + optional profile fields |
| `access` | `array\|null` | No | Policy IDs or inline sources + filters |
| `origin` | `string\|null` | No | Allowed origin for the embed iframe (CORS validation) |
| `ttl` | `int` | No | Session lifetime in seconds (default: `3600`) |

### Return Value: `GetSessionResult`

| Property | Type | Description |
|---|---|---|
| `sessionToken` | `string` | Pass this to the embed component's `fetchSessionToken` |
| `expiresIn` | `int` | Seconds until the token expires |
| `userId` | `string` | Querri internal user ID |
| `externalId` | `string\|null` | Your external ID for the user |

`GetSessionResult` implements `JsonSerializable`, so you can pass it directly to `json_encode()`:

```php
header('Content-Type: application/json');
echo json_encode($session);
// {"session_token":"...","expires_in":3600,"user_id":"...","external_id":"..."}
```

### User Resolution

The `user` parameter supports two forms:

**String shorthand** — pass an external ID directly. The SDK calls `users->getOrCreate($externalId)` with no additional fields:

```php
$client->getSession([
    'user' => 'usr_alice',
]);
```

**Array form** — pass an `external_id` along with optional profile fields. All fields are forwarded to `users->getOrCreate()`:

```php
$client->getSession([
    'user' => [
        'external_id' => 'usr_alice',
        'email' => 'alice@example.com',
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'role' => 'viewer',
    ],
]);
```

In both cases, if the user already exists, they are returned as-is (the profile fields are used at creation time).

### Access Policies

The `access` parameter controls what data the user can see. It supports two forms:

**Policy ID reference** — attach the user to one or more existing policies by ID:

```php
'access' => [
    'policy_ids' => ['pol_abc', 'pol_def'],
],
```

**Inline sources + filters** — specify the allowed sources and row-level filters directly. The SDK automatically creates and manages a policy for this configuration:

```php
'access' => [
    'sources' => ['src_sales', 'src_inventory'],
    'filters' => [
        'tenant_id' => 'acme',
        'region' => ['us-east', 'us-west'],   // Array values are OR'd
    ],
],
```

### Deterministic Policy Hashing

When you use inline access, the SDK does not create a new policy on every call. Instead, it:

1. Sorts the `sources` array and `filters` keys alphabetically.
2. Normalizes filter values to sorted arrays.
3. Computes a SHA-256 hash of the resulting JSON.
4. Truncates the hash to 8 hex characters.
5. Names the policy `sdk_auto_{hash}` (e.g., `sdk_auto_a1b2c3d4`).

On subsequent calls with the same sources and filters, the SDK finds the existing policy by name and reuses it. This means:

- Identical access specs always map to the same policy.
- You do not accumulate duplicate policies over time.
- The user is assigned to the policy if not already assigned.
- The hash is cross-SDK compatible — the JS and PHP SDKs produce identical hashes for the same input.

### Race Condition Handling

When two concurrent requests try to create the same auto-managed policy, the SDK handles the TOCTOU (time-of-check-time-of-use) race condition:

1. Request A checks for policy — not found.
2. Request B checks for policy — not found.
3. Request A creates the policy — success.
4. Request B tries to create the same policy — gets a `409 Conflict`.
5. Request B catches the conflict, re-fetches the policy by name, and proceeds.

---

## Error Handling

### Error Hierarchy

```
QuerriException (extends RuntimeException)
├── ConfigException          — Missing/invalid SDK configuration
├── ConnectionException      — Network-level failures
│   └── TimeoutException     — Request timed out
└── ApiException             — HTTP error response from API
    ├── ValidationException  — 400 Bad Request
    ├── AuthenticationException — 401 Unauthorized
    ├── PermissionException  — 403 Forbidden
    ├── NotFoundException    — 404 Not Found
    ├── ConflictException    — 409 Conflict
    ├── RateLimitException   — 429 Too Many Requests
    └── ServerException      — 5xx Server Error
```

### ApiException Properties

All `ApiException` subclasses expose:

| Property | Type | Description |
|---|---|---|
| `$status` | `int` | HTTP status code |
| `$message` | `string` | Human-readable error message |
| `$body` | `mixed` | Raw decoded response body |
| `$headers` | `array` | Response headers |
| `$requestId` | `string\|null` | Request ID for support tickets |
| `$type` | `string\|null` | Error type from the API |
| `$code` | `string\|null` | Error code from the API |
| `$docUrl` | `string\|null` | Link to relevant documentation |

### Common Patterns

```php
use Querri\Embed\Exceptions\ApiException;
use Querri\Embed\Exceptions\AuthenticationException;
use Querri\Embed\Exceptions\NotFoundException;
use Querri\Embed\Exceptions\RateLimitException;
use Querri\Embed\Exceptions\ValidationException;
use Querri\Embed\Exceptions\ConfigException;
use Querri\Embed\Exceptions\ConnectionException;

try {
    $user = $client->users->retrieve('user_nonexistent');
} catch (NotFoundException $e) {
    echo "User not found";
} catch (AuthenticationException $e) {
    echo "Invalid API key";
} catch (RateLimitException $e) {
    echo "Rate limited. Retry after {$e->retryAfter} seconds";
} catch (ValidationException $e) {
    echo "Bad request: {$e->getMessage()}";
} catch (ConnectionException $e) {
    echo "Network error: {$e->getMessage()}";
} catch (ApiException $e) {
    echo "API error {$e->status}: {$e->getMessage()}";
    echo "Request ID: {$e->requestId}";
} catch (ConfigException $e) {
    echo "Config error: {$e->getMessage()}";
}
```

> **Tip:** Always catch specific exceptions before general ones. PHP resolves `catch` blocks top-down, so `ApiException` must come after `NotFoundException`, `RateLimitException`, etc.

### Automatic Retry Behavior

The SDK automatically retries failed requests under these conditions:

- **429 (Rate Limited)**: Always retried, regardless of HTTP method.
- **500, 502, 503, 504 (Server Errors)**: Retried only for idempotent methods (`GET`, `PUT`, `DELETE`, `HEAD`, `OPTIONS`).
- **Timeouts and connection errors**: Retried only for idempotent methods.

Retry delays use exponential backoff starting at 500ms, with ±25% jitter, capped at 30 seconds. The `Retry-After` header is respected when present.

The maximum number of retries defaults to 3 and can be configured via `max_retries` in the client config.

### Rate Limit Backoff

`RateLimitException` includes a `retryAfter` property (in seconds), parsed from the `Retry-After` response header:

```php
try {
    $client->users->list();
} catch (RateLimitException $e) {
    if ($e->retryAfter !== null) {
        sleep((int) ceil($e->retryAfter));
        // Retry the request
    }
}
```

In practice, you rarely need manual retry logic — the SDK retries 429s automatically up to `max_retries` times.

---

## Framework Guides

### Plain PHP

For apps without a framework (vanilla PHP, WordPress, etc.), create a PHP file that serves as your session endpoint.

#### File Structure

```
your-app/
├── composer.json
├── vendor/
├── public/
│   ├── index.html
│   └── api/
│       └── querri-session.php
```

#### `public/api/querri-session.php`

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Querri\Embed\QuerriClient;
use Querri\Embed\Exceptions\ApiException;
use Querri\Embed\Exceptions\QuerriException;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

try {
    $client = new QuerriClient();  // reads QUERRI_API_KEY from env

    // Replace with your auth logic
    $authUser = getAuthenticatedUser();

    $session = $client->getSession([
        'user' => [
            'external_id' => $authUser->id,
            'email' => $authUser->email,
        ],
        'access' => [
            'sources' => ['src_sales_data'],
            'filters' => ['tenant_id' => $authUser->tenantId],
        ],
        'ttl' => 3600,
    ]);

    echo json_encode($session);
} catch (ApiException $e) {
    http_response_code($e->status >= 400 ? $e->status : 500);
    echo json_encode(['error' => $e->getMessage(), 'code' => $e->code]);
} catch (QuerriException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
```

#### Client Component (React)

```tsx
'use client';

import { useMemo } from 'react';
import { QuerriEmbed } from '@querri-inc/embed/react';

export default function DashboardPage() {
  const auth = useMemo(() => ({
    fetchSessionToken: async () => {
      const res = await fetch('/api/querri-session.php', { method: 'POST' });
      const { session_token } = await res.json();
      return session_token;
    },
  }), []);

  return (
    <div style={{ width: '100%', height: '600px' }}>
      <QuerriEmbed
        serverUrl="https://app.querri.com"
        auth={auth}
        startView="/builder/dashboard/your-dashboard-uuid"
        onReady={() => console.log('Loaded')}
        onError={(err) => console.error(err)}
      />
    </div>
  );
}
```

#### How It Works

1. The React component's `fetchSessionToken` is called when the embed mounts.
2. The POST request hits your `querri-session.php` endpoint.
3. The PHP script resolves the user from your auth system, creates/reuses access policies, and returns a session token.
4. The embed uses the token to authenticate the iframe connection.

---

### Laravel

#### Install

```bash
composer require querri/embed
```

Add your API key to `.env`:

```
QUERRI_API_KEY=qk_your_api_key
QUERRI_ORG_ID=org_your_org_id
```

#### Route: `routes/api.php`

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Querri\Embed\QuerriClient;
use Querri\Embed\Exceptions\ApiException;
use Querri\Embed\Exceptions\QuerriException;

Route::post('/querri-session', function (Request $request) {
    $client = new QuerriClient();
    $user = $request->user();

    try {
        $session = $client->getSession([
            'user' => [
                'external_id' => (string) $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            ],
            'access' => [
                'sources' => ['src_sales_data'],
                'filters' => ['tenant_id' => $user->tenant_id],
            ],
            'ttl' => 3600,
        ]);

        return response()->json($session);
    } catch (ApiException $e) {
        return response()->json(
            ['error' => $e->getMessage()],
            $e->status >= 400 ? $e->status : 500,
        );
    } catch (QuerriException $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
})->middleware('auth:sanctum');
```

#### Client Component (Blade + React)

```tsx
// resources/js/components/Dashboard.tsx
import { useMemo } from 'react';
import { QuerriEmbed } from '@querri-inc/embed/react';

export default function Dashboard() {
  const auth = useMemo(() => ({
    fetchSessionToken: async () => {
      const res = await fetch('/api/querri-session', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        },
      });
      const { session_token } = await res.json();
      return session_token;
    },
  }), []);

  return (
    <div style={{ width: '100%', height: '600px' }}>
      <QuerriEmbed
        serverUrl="https://app.querri.com"
        auth={auth}
        startView="/builder/dashboard/your-dashboard-uuid"
      />
    </div>
  );
}
```

#### How It Works

1. The React component's `fetchSessionToken` fires when the embed mounts.
2. The POST request hits your Laravel route with Sanctum auth.
3. The route resolves the authenticated user, creates/reuses access policies, and returns a session token.
4. The embed authenticates the iframe with the returned token.

---

### Symfony

#### Install

```bash
composer require querri/embed
```

Add your API key to `.env`:

```
QUERRI_API_KEY=qk_your_api_key
QUERRI_ORG_ID=org_your_org_id
```

#### Controller: `src/Controller/QuerriSessionController.php`

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Querri\Embed\QuerriClient;
use Querri\Embed\Exceptions\ApiException;
use Querri\Embed\Exceptions\QuerriException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class QuerriSessionController extends AbstractController
{
    #[Route('/api/querri-session', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function __invoke(): JsonResponse
    {
        $client = new QuerriClient();
        $user = $this->getUser();

        try {
            $session = $client->getSession([
                'user' => [
                    'external_id' => $user->getUserIdentifier(),
                    'email' => $user->getEmail(),
                ],
                'access' => [
                    'sources' => ['src_sales_data'],
                    'filters' => ['tenant_id' => $user->getTenantId()],
                ],
            ]);

            return $this->json($session);
        } catch (ApiException $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                $e->status >= 400 ? $e->status : 500,
            );
        } catch (QuerriException $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
```

#### How It Works

1. The frontend embed component calls `fetchSessionToken()` at mount time.
2. The POST request hits your Symfony controller.
3. The controller resolves the authenticated user from the Symfony security system, manages access policies, and returns a session token.
4. The embed uses the token to authenticate the iframe.

---

### React + PHP (Vite)

For a React frontend with a PHP backend during development, use Vite's proxy to forward API requests to PHP's built-in server. See [`examples/react-embed/`](../examples/react-embed/) for a complete working example.

#### File Structure

```
your-app/
├── composer.json
├── vendor/
├── public/
│   └── api/
│       └── querri-session.php
├── src/
│   ├── App.tsx
│   └── main.tsx
├── package.json
├── vite.config.ts
└── index.html
```

#### `vite.config.ts`

In development, Vite proxies `/api` requests to PHP's built-in server:

```typescript
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': 'http://localhost:8080',
    },
  },
});
```

#### Running in Development

```bash
# Terminal 1: Start PHP built-in server
php -S localhost:8080 -t public

# Terminal 2: Start Vite dev server
npm run dev
```

The Vite dev server (typically `:5173`) proxies `/api/*` to the PHP server (`:8080`), so no CORS configuration is needed.

#### `src/App.tsx`

```tsx
import { useMemo } from 'react';
import { QuerriEmbed } from '@querri-inc/embed/react';

export default function App() {
  const auth = useMemo(() => ({
    fetchSessionToken: async () => {
      const res = await fetch('/api/querri-session.php', { method: 'POST' });
      const { session_token } = await res.json();
      return session_token;
    },
  }), []);

  return (
    <div style={{ width: '100%', height: '100vh' }}>
      <QuerriEmbed
        serverUrl="https://app.querri.com"
        auth={auth}
        startView="/builder/dashboard/your-dashboard-uuid"
        onReady={() => console.log('Querri embed loaded')}
        onError={(err) => console.error('Embed error:', err)}
      />
    </div>
  );
}
```

#### How It Works

1. The React component's `fetchSessionToken` fires on mount.
2. Vite proxies the POST to the PHP built-in server.
3. `querri-session.php` creates a session via the Querri SDK and returns the token.
4. The embed uses the token to authenticate the iframe.

---

## Requirements

- PHP 8.4+
- `ext-json`
- `ext-hash`
- [`symfony/http-client`](https://symfony.com/doc/current/http_client.html) ^7.2

## Differences from the JS SDK

| Feature | JS SDK (`@querri-inc/embed`) | PHP SDK (`querri/embed`) |
|---|---|---|
| Framework helpers | `createSessionHandler()` for Next.js, SvelteKit, etc. | Use your framework's routing directly |
| Async iteration | `for await...of` on paginated results | Not applicable (synchronous) |
| Streaming | `ChatStream` for SSE responses | Not implemented (embed-focused) |
| Additional resources | Projects, Chats, Dashboards, Data, Files, Sources, Keys, Sharing, Audit, Usage | Users, Embed, Policies (core embed resources) |
| Config keys | `camelCase` only | Both `camelCase` and `snake_case` accepted |
| HTTP client | Built-in `fetch` | Symfony HttpClient (HTTP/2 native) |
| Policy hash | `hashAccessSpec()` in TypeScript | Identical algorithm in PHP — cross-SDK compatible |

The PHP SDK focuses on the embed use case. For resources not covered (Projects, Dashboards, etc.), use the [HTTP API directly](https://app.querri.com/docs/api).
