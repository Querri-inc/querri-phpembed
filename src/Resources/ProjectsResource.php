<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Projects API — CRUD, execution, and step data for analysis projects.
 */
final class ProjectsResource extends BaseResource
{
    public function list(?array $params = null): array
    {
        return $this->get('/projects', $params);
    }

    /**
     * @param array{name: string, description?: string, user_id: string} $params
     */
    public function create(array $params): array
    {
        return $this->post('/projects', $params);
    }

    public function retrieve(string $projectId): array
    {
        return $this->get('/projects/' . rawurlencode($projectId));
    }

    /**
     * @param array{name?: string, description?: string} $params
     */
    public function update(string $projectId, array $params): array
    {
        return $this->put('/projects/' . rawurlencode($projectId), $params);
    }

    public function del(string $projectId): array
    {
        return $this->delete('/projects/' . rawurlencode($projectId));
    }

    /**
     * @param array{user_id: string} $params
     */
    public function run(string $projectId, array $params): array
    {
        return $this->post('/projects/' . rawurlencode($projectId) . '/run', $params);
    }

    public function runStatus(string $projectId): array
    {
        return $this->get('/projects/' . rawurlencode($projectId) . '/run/status');
    }

    public function runCancel(string $projectId): array
    {
        return $this->post('/projects/' . rawurlencode($projectId) . '/run/cancel');
    }

    public function listSteps(string $projectId): array
    {
        return $this->get('/projects/' . rawurlencode($projectId) . '/steps');
    }

    public function getStepData(string $projectId, string $stepId, ?array $params = null): array
    {
        return $this->get(
            '/projects/' . rawurlencode($projectId) . '/steps/' . rawurlencode($stepId) . '/data',
            $params,
        );
    }
}
