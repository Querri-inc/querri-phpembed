<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Sources & Connectors API — manage data sources and their connectors.
 */
final class SourcesResource extends BaseResource
{
    /**
     * @param array{limit?: int, after?: string}|null $params
     * @return array{data: array<int, array<string, mixed>>, has_more: bool, next_cursor: string|null}
     */
    public function listConnectors(?array $params = null): array
    {
        return $this->get('/connectors', $params);
    }

    /**
     * @param array{limit?: int, after?: string}|null $params
     * @return array{data: array<int, array<string, mixed>>, has_more: bool, next_cursor: string|null}
     */
    public function list(?array $params = null): array
    {
        return $this->get('/sources', $params);
    }

    /**
     * @param array{name: string, connector_id: string, config: array<string, mixed>} $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->post('/sources', $params);
    }

    /**
     * @param array{name?: string, config?: array<string, mixed>} $params
     * @return array<string, mixed>
     */
    public function update(string $sourceId, array $params): array
    {
        return $this->patch('/sources/' . rawurlencode($sourceId), $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function del(string $sourceId): array
    {
        return $this->delete('/sources/' . rawurlencode($sourceId));
    }

    /**
     * @return array<string, mixed>
     */
    public function sync(string $sourceId): array
    {
        return $this->post('/sources/' . rawurlencode($sourceId) . '/sync');
    }
}
