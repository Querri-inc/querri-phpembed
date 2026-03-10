<?php

declare(strict_types=1);

/**
 * SDK Explorer Action Router
 *
 * POST /api/sdk.php
 * Body: { "action": "users.list", "params": { ... } }
 *
 * Single endpoint that dispatches to any PHP SDK method.
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
$action = $input['action'] ?? '';
$params = $input['params'] ?? [];

try {
    $client = new QuerriClient();

    $result = match ($action) {
        // ─── Users ───────────────────────────────────────
        'users.list'             => $client->users->list($params ?: null),
        'users.create'           => $client->users->create($params),
        'users.retrieve'         => $client->users->retrieve($params['user_id']),
        'users.update'           => $client->users->update($params['user_id'], $params['data'] ?? []),
        'users.del'              => $client->users->del($params['user_id']),
        'users.getOrCreate'      => $client->users->getOrCreate($params['external_id'], $params['data'] ?? null),
        'users.removeExternalId' => $client->users->removeExternalId($params['external_id']),

        // ─── Embed ───────────────────────────────────────
        'embed.createSession'      => $client->embed->createSession($params),
        'embed.refreshSession'     => $client->embed->refreshSession($params['session_token']),
        'embed.listSessions'       => $client->embed->listSessions($params ?: null),
        'embed.revokeSession'      => $client->embed->revokeSession($params['session_id']),
        'embed.revokeUserSessions' => ['revoked' => $client->embed->revokeUserSessions($params['user_id'])],

        // ─── Policies ────────────────────────────────────
        'policies.list'        => $client->policies->list($params ?: null),
        'policies.create'      => $client->policies->create($params),
        'policies.retrieve'    => $client->policies->retrieve($params['policy_id']),
        'policies.update'      => $client->policies->update($params['policy_id'], $params['data'] ?? []),
        'policies.del'         => $client->policies->del($params['policy_id']),
        'policies.assignUsers' => $client->policies->assignUsers($params['policy_id'], $params['user_ids']),
        'policies.removeUser'  => $client->policies->removeUser($params['policy_id'], $params['user_id']),
        'policies.resolve'     => $client->policies->resolve($params['user_id'], $params['source_id']),
        'policies.columns'     => $client->policies->columns($params['source_id'] ?? null),

        // ─── Dashboards ──────────────────────────────────
        'dashboards.list'          => $client->dashboards->list($params ?: null),
        'dashboards.create'        => $client->dashboards->create($params),
        'dashboards.retrieve'      => $client->dashboards->retrieve($params['dashboard_id']),
        'dashboards.update'        => $client->dashboards->update($params['dashboard_id'], $params['data'] ?? []),
        'dashboards.del'           => $client->dashboards->del($params['dashboard_id']),
        'dashboards.refresh'       => $client->dashboards->refresh($params['dashboard_id']),
        'dashboards.refreshStatus' => $client->dashboards->refreshStatus($params['dashboard_id']),

        // ─── Projects ────────────────────────────────────
        'projects.list'        => $client->projects->list($params ?: null),
        'projects.create'      => $client->projects->create($params),
        'projects.retrieve'    => $client->projects->retrieve($params['project_id']),
        'projects.update'      => $client->projects->update($params['project_id'], $params['data'] ?? []),
        'projects.del'         => $client->projects->del($params['project_id']),
        'projects.run'         => $client->projects->run($params['project_id'], ['user_id' => $params['user_id']]),
        'projects.runStatus'   => $client->projects->runStatus($params['project_id']),
        'projects.runCancel'   => $client->projects->runCancel($params['project_id']),
        'projects.listSteps'   => $client->projects->listSteps($params['project_id']),
        'projects.getStepData' => $client->projects->getStepData(
            $params['project_id'],
            $params['step_id'],
            array_filter(['page' => $params['page'] ?? null, 'page_size' => $params['page_size'] ?? null]),
        ),

        // ─── Chats ───────────────────────────────────────
        'chats.create'   => $client->chats->create($params['project_id'], $params['data'] ?? []),
        'chats.list'     => $client->chats->list($params['project_id'], $params['data'] ?? null),
        'chats.retrieve' => $client->chats->retrieve($params['project_id'], $params['chat_id']),
        'chats.del'      => $client->chats->del($params['project_id'], $params['chat_id']),
        'chats.cancel'   => $client->chats->cancel($params['project_id'], $params['chat_id']),

        // ─── Data ────────────────────────────────────────
        'data.listSources'   => $client->data->listSources($params ?: null),
        'data.getSource'     => $client->data->getSource($params['source_id']),
        'data.createSource'  => $client->data->createSource($params),
        'data.appendRows'    => $client->data->appendRows($params['source_id'], ['rows' => $params['rows']]),
        'data.replaceData'   => $client->data->replaceData($params['source_id'], ['rows' => $params['rows']]),
        'data.deleteSource'  => $client->data->deleteSource($params['source_id']),
        'data.query'         => $client->data->query($params),
        'data.getSourceData' => $client->data->getSourceData(
            $params['source_id'],
            array_filter(['page' => $params['page'] ?? null, 'page_size' => $params['page_size'] ?? null]),
        ),

        // ─── Sources & Connectors ────────────────────────
        'sources.listConnectors' => $client->sources->listConnectors($params ?: null),
        'sources.list'           => $client->sources->list($params ?: null),
        'sources.create'         => $client->sources->create($params),
        'sources.update'         => $client->sources->update($params['source_id'], $params['data'] ?? []),
        'sources.del'            => $client->sources->del($params['source_id']),
        'sources.sync'           => $client->sources->sync($params['source_id']),

        // ─── Files ───────────────────────────────────────
        'files.list'     => $client->files->list($params ?: null),
        'files.retrieve' => $client->files->retrieve($params['file_id']),
        'files.del'      => $client->files->del($params['file_id']),

        // ─── API Keys ────────────────────────────────────
        'keys.create'  => $client->keys->create($params),
        'keys.list'    => $client->keys->list($params ?: null),
        'keys.retrieve'=> $client->keys->retrieve($params['key_id']),
        'keys.revoke'  => $client->keys->revoke($params['key_id']),

        // ─── Audit ───────────────────────────────────────
        'audit.listEvents' => $client->audit->listEvents($params ?: null),

        // ─── Usage ───────────────────────────────────────
        'usage.org'  => $client->usage->getOrgUsage($params['period'] ?? 'current_month'),
        'usage.user' => $client->usage->getUserUsage($params['user_id'], $params['period'] ?? 'current_month'),

        // ─── Sharing ─────────────────────────────────────
        'sharing.shareProject'          => $client->sharing->shareProject($params['project_id'], ['user_id' => $params['user_id'], 'permission' => $params['permission'] ?? 'view']),
        'sharing.revokeProjectShare'    => $client->sharing->revokeProjectShare($params['project_id'], $params['user_id']),
        'sharing.listProjectShares'     => $client->sharing->listProjectShares($params['project_id']),
        'sharing.shareDashboard'        => $client->sharing->shareDashboard($params['dashboard_id'], ['user_id' => $params['user_id'], 'permission' => $params['permission'] ?? 'view']),
        'sharing.revokeDashboardShare'  => $client->sharing->revokeDashboardShare($params['dashboard_id'], $params['user_id']),
        'sharing.listDashboardShares'   => $client->sharing->listDashboardShares($params['dashboard_id']),
        'sharing.shareSource'           => $client->sharing->shareSource($params['source_id'], ['user_id' => $params['user_id'], 'permission' => $params['permission'] ?? 'view']),
        'sharing.orgShareSource'        => $client->sharing->orgShareSource($params['source_id'], ['enabled' => $params['enabled'], 'permission' => $params['permission'] ?? 'view']),

        default => throw new \InvalidArgumentException("Unknown action: {$action}"),
    };

    echo json_encode($result);
} catch (ApiException $e) {
    http_response_code($e->status >= 400 ? $e->status : 500);
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => $e->errorCode,
        'type' => $e->type,
        'status' => $e->status,
    ]);
} catch (QuerriException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (\InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
