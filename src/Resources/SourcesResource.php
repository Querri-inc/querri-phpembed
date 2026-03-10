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
     */
    public function listConnectors(?array $params = null): array
    {
        return $this->get('/connectors', $params);
    }

    /**
     * @param array{limit?: int, after?: string}|null $params
     */
    public function list(?array $params = null): array
    {
        return $this->get('/sources', $params);
    }

    /**
     * @param array{name: string, connector_id: string, config: array} $params
     */
    public function create(array $params): array
    {
        return $this->post('/sources', $params);
    }

    /**
     * @param array{name?: string, config?: array} $params
     */
    public function update(string $sourceId, array $params): array
    {
        return $this->patch('/sources/' . rawurlencode($sourceId), $params);
    }

    public function del(string $sourceId): array
    {
        return $this->delete('/sources/' . rawurlencode($sourceId));
    }

    public function sync(string $sourceId): array
    {
        return $this->post('/sources/' . rawurlencode($sourceId) . '/sync');
    }
}
