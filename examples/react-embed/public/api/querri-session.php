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

    $session = $client->getSession([
        'user' => [
            'external_id' => 'john-sim-querri',
            'email'       => 'john.sim@querri.com',
            'first_name'  => 'John',
            'last_name'   => 'Sim',
        ],
        'access' => [
            'sources' => ['franchisor_financial_reports_202602190025.csv'],
            'filters' => [
                'store_id' => [
                    '7434','3451','9273','8213','6834','4293','3353','3352','2903','6835','2923','7435','3417','3412','3413','4933','3232','7436','7532','3285','5361','10314','10313','2873','7437','2931','3883','4396','5354','5339','3332','7239','3225','5939','5350','6935','7443','7301','6236','10312','10435','9473','9389','10643'
                ],
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
