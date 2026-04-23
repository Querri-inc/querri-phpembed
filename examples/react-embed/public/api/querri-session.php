<?php

declare(strict_types=1);

/**
 * Querri Embed Session Endpoint
 *
 * POST /api/querri-session
 *
 * This is the PHP equivalent of the Express/Next.js createSessionHandler().
 * In production, you would extract user identity from your auth system
 * (e.g., JWT, session cookie, OAuth token).
 */

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Querri\Embed\QuerriClient;
use Querri\Embed\Exceptions\ApiException;
use Querri\Embed\Exceptions\QuerriException;

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

try {
    // Load .env if using dotenv, or set env vars in your server config.
    // The SDK reads QUERRI_API_KEY and QUERRI_ORG_ID from environment.
    $client = new QuerriClient();

    // ------------------------------------------------------------------
    // In production, extract the user from YOUR auth system:
    //
    //   $authUser = getUserFromSession($_COOKIE['session_id']);
    //   $session = $client->getSession([
    //       'user' => [
    //           'external_id' => $authUser->id,
    //           'email'       => $authUser->email,
    //           'first_name'  => $authUser->firstName,
    //       ],
    //       'access' => [
    //           'sources' => ['src_sales_data'],
    //           'filters' => ['tenant_id' => $authUser->tenantId],
    //       ],
    //       'origin' => $_SERVER['HTTP_ORIGIN'] ?? null,
    //       'ttl' => 3600,
    //   ]);
    //
    // ------------------------------------------------------------------

    // Demo payload — replace the user identity with your real user, and
    // replace the access block with the sources and row-level filters that
    // apply to them. For local testing, point `sources` at a source name or
    // UUID that exists in your Querri org.
    $session = $client->getSession([
        'user' => [
            'external_id' => 'demo-user-123',
            'email'       => 'demo.user@example.com',
            'first_name'  => 'Demo',
            'last_name'   => 'User',
        ],
        'access' => [
            // List the sources this user is allowed to query (by name or UUID).
            'sources' => ['your_source_name_or_uuid'],
            // Optional: restrict rows by column values. Example shown; remove
            // or adapt to your schema.
            'filters' => [
                'tenant_id' => ['tenant_1', 'tenant_2'],
            ],
        ],
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? null,
        'ttl' => 3600,
    ]);

    echo json_encode($session);
} catch (ApiException $e) {
    http_response_code($e->status >= 400 ? $e->status : 500);
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => $e->errorCode,
    ]);
} catch (QuerriException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
