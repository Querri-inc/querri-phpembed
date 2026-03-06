<?php

declare(strict_types=1);

/**
 * User-Filtered Project List Example
 *
 * POST /api/user-projects.php
 * Body: { "external_id": "demo-user", "email": "john@example.com" }
 *
 * Demonstrates how to get a per-user, FGA-filtered project list:
 *
 * 1. Create an embed session for the user (resolves user + creates session)
 * 2. Create a user-scoped client via asUser()
 * 3. List projects — the internal API applies FGA filtering automatically
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
    $session = $client->getSession([
        'user' => array_filter([
            'external_id' => $externalId,
            'email'       => $input['email'] ?? null,
            'first_name'  => $input['first_name'] ?? null,
            'last_name'   => $input['last_name'] ?? null,
        ]),
        'ttl' => 900,
    ]);

    // Step 2: Create a user-scoped client and list FGA-filtered projects.
    $userClient = $client->asUser($session);
    $projects = $userClient->projects->list();

    echo json_encode([
        'user_external_id' => $externalId,
        'session_token' => $session->sessionToken,
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
