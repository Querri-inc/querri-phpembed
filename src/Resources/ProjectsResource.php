<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Projects API — CRUD, execution, and step data for analysis projects.
 */
final class ProjectsResource extends BaseResource
{
    /**
     * List projects. Optionally filter by user (FGA-filtered).
     *
     * @param array{user_id?: string, limit?: int, after?: string}|null $params
     *   user_id: WorkOS user ID or external ID — returns only projects the user can access
     * @return array{data: array<int, array<string, mixed>>, has_more: bool, next_cursor: string|null}
     */
    public function list(?array $params = null): array
    {
        return $this->get('/projects', $params);
    }

    /**
     * @param array{name: string, description?: string, user_id: string} $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->post('/projects', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieve(string $projectId): array
    {
        return $this->get('/projects/' . rawurlencode($projectId));
    }

    /**
     * @param array{name?: string, description?: string} $params
     * @return array<string, mixed>
     */
    public function update(string $projectId, array $params): array
    {
        return $this->put('/projects/' . rawurlencode($projectId), $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function del(string $projectId): array
    {
        return $this->delete('/projects/' . rawurlencode($projectId));
    }

    /**
     * @param array{user_id: string} $params
     * @return array<string, mixed>
     */
    public function run(string $projectId, array $params): array
    {
        return $this->post('/projects/' . rawurlencode($projectId) . '/run', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function runStatus(string $projectId): array
    {
        return $this->get('/projects/' . rawurlencode($projectId) . '/run/status');
    }

    /**
     * @return array<string, mixed>
     */
    public function runCancel(string $projectId): array
    {
        return $this->post('/projects/' . rawurlencode($projectId) . '/run/cancel');
    }

    /**
     * @return array<string, mixed>
     */
    public function listSteps(string $projectId): array
    {
        return $this->get('/projects/' . rawurlencode($projectId) . '/steps');
    }

    /**
     * @param array<string, mixed>|null $params
     * @return array<string, mixed>
     */
    public function getStepData(string $projectId, string $stepId, ?array $params = null): array
    {
        return $this->get(
            '/projects/' . rawurlencode($projectId) . '/steps/' . rawurlencode($stepId) . '/data',
            $params,
        );
    }
}
