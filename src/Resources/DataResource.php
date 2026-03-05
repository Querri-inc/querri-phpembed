<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Data API — query data sources with automatic RLS enforcement.
 */
final class DataResource extends BaseResource
{
    public function listSources(): array
    {
        return $this->get('/data/sources');
    }

    public function getSource(string $sourceId): array
    {
        return $this->get('/data/sources/' . rawurlencode($sourceId));
    }

    /**
     * Execute a SQL query against a data source.
     *
     * @param array{sql: string, source_id: string, page?: int, page_size?: int} $params
     */
    public function query(array $params): array
    {
        return $this->post('/data/query', $params);
    }

    /**
     * Get paginated data from a source.
     */
    public function getSourceData(string $sourceId, ?array $params = null): array
    {
        return $this->get('/data/sources/' . rawurlencode($sourceId) . '/data', $params);
    }
}
