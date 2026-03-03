# Querri Embed — PHP + React Example

This example demonstrates embedding Querri analytics using a **PHP backend** with the [Querri PHP SDK](../../) and a **React frontend** with `@querri-inc/embed/react`.

## How It Works

```
Browser (React)                    PHP Server                      Querri API
      │                                │                               │
      │  POST /api/querri-session.php  │                               │
      ├───────────────────────────────>│                               │
      │                                │  PUT /users/external/{id}     │
      │                                ├──────────────────────────────>│
      │                                │<──────────────────────────────┤
      │                                │  POST /access/policies        │
      │                                ├──────────────────────────────>│
      │                                │<──────────────────────────────┤
      │                                │  POST /embed/sessions         │
      │                                ├──────────────────────────────>│
      │                                │<──────────────────────────────┤
      │  { session_token, expires_in } │                               │
      │<───────────────────────────────┤                               │
      │                                                                │
      │  Load embed iframe with token                                  │
      ├───────────────────────────────────────────────────────────────>│
```

## Prerequisites

- PHP 8.4+
- Node.js 18+
- Composer

## Setup

1. **Install PHP dependencies** (from the repository root):

   ```bash
   composer install
   ```

2. **Install Node.js dependencies**:

   ```bash
   cd examples/react-embed
   npm install
   ```

3. **Configure environment variables**:

   ```bash
   cp .env.example .env
   ```

   Edit `.env` and set your `QUERRI_API_KEY` and `QUERRI_ORG_ID`.

4. **Start the development servers**:

   ```bash
   npm run dev
   ```

   This runs both:
   - **PHP built-in server** on `http://localhost:8080` (serves the API)
   - **Vite dev server** on `http://localhost:5173` (serves React, proxies `/api` to PHP)

5. Open `http://localhost:5173` in your browser.

## Production

For production, build the React app and serve everything through your web server (nginx/Apache):

```bash
npm run build
```

This outputs the built React app to `public/dist/`. Configure your web server to serve the `public/` directory and route PHP requests through PHP-FPM.

## Customization

### Adding Real Authentication

Replace the demo user in `public/api/querri-session.php` with your actual auth logic:

```php
// Extract user from your auth system
$authUser = getUserFromSession($_COOKIE['session_id']);

$session = $client->getSession([
    'user' => [
        'external_id' => $authUser->id,
        'email'       => $authUser->email,
        'first_name'  => $authUser->firstName,
    ],
    'access' => [
        'sources' => ['src_sales_data'],
        'filters' => ['tenant_id' => $authUser->tenantId],
    ],
    'ttl' => 3600,
]);
```

### Using Pre-Created Policies

Instead of inline access rules, reference existing policy IDs:

```php
$session = $client->getSession([
    'user' => ['external_id' => $authUser->id],
    'access' => ['policy_ids' => ['pol_abc123']],
]);
```
