<?php

declare(strict_types=1);

/**
 * User-Filtered Project List Example
 *
 * POST /api/user-projects.php
 * Body: { "external_id": "demo-user", "email": "john@example.com" }
 *
 * Demonstrates how to get a per-user project list using an embed session:
 *
 * 1. Create an embed session for the user via the public API
 * 2. Use that session token to call the internal /api/projects endpoint
 * 3. The internal endpoint applies FGA filtering — only projects the user
 *    has "viewer" access to are returned
 *
 * This is useful when your PHP backend needs to know which projects a
 * specific embed user can see, before rendering the embedded UI.
 */

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Querri\Embed\QuerriClient;
use Querri\Embed\Exceptions\ApiException;
use Querri\Embed\Exceptions\QuerriException;
use Symfony\Component\HttpClient\HttpClient;

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$externalId = $input['external_id'] ?? '';

if ($externalId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'external_id is required']);
    exit;
}

try {
    $client = new QuerriClient();

    // Step 1: Create an embed session for this user.
    // getSession() resolves the user (getOrCreate) and creates a session in one call.
    $session = $client->getSession([
        'user' => array_filter([
            'external_id' => $externalId,
            'email'       => $input['email'] ?? null,
            'first_name'  => $input['first_name'] ?? null,
            'last_name'   => $input['last_name'] ?? null,
        ]),
        'ttl' => 900, // Minimum allowed TTL is 900s (15 minutes)
    ]);

    $sessionToken = $session->sessionToken;

    // Step 2: Derive the base host from QUERRI_URL (same env var the SDK uses).
    // The SDK's Config appends /api/v1, but we need the bare host for internal API.
    $host = getenv('QUERRI_URL') ?: ($_ENV['QUERRI_URL'] ?? $_SERVER['QUERRI_URL'] ?? 'https://app.querri.com');
    $host = rtrim($host, '/');

    // Step 3: Call the internal /api/projects endpoint using the embed session token.
    // This endpoint applies FGA filtering — only projects the user has access to.
    $httpClient = HttpClient::create(['timeout' => 15]);
    $response = $httpClient->request('GET', "{$host}/api/projects", [
        'headers' => [
            'X-Embed-Session' => $sessionToken,
            'Accept' => 'application/json',
        ],
    ]);

    $statusCode = $response->getStatusCode();

    if ($statusCode >= 400) {
        http_response_code($statusCode);
        echo json_encode([
            'error' => 'Failed to fetch user projects',
            'status' => $statusCode,
            'detail' => $response->getContent(false),
        ]);
        exit;
    }

    $projects = $response->toArray(false);

    // Return the filtered project list along with the session token
    // (useful if you also want to embed the UI afterward).
    echo json_encode([
        'user_external_id' => $externalId,
        'session_token' => $sessionToken,
        'projects' => $projects,
    ]);
} catch (ApiException $e) {
    http_response_code($e->status >= 400 ? $e->status : 500);
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => $e->errorCode,
    ]);
} catch (QuerriException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
