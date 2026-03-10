<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Data API — query data sources with automatic RLS enforcement.
 */
final class DataResource extends BaseResource
{
    /**
     * @param array{limit?: int, after?: string}|null $params
     */
    public function listSources(?array $params = null): array
    {
        return $this->get('/data/sources', $params);
    }

    public function getSource(string $sourceId): array
    {
        return $this->get('/data/sources/' . rawurlencode($sourceId));
    }

    /**
     * Create a data source with inline JSON rows.
     *
     * @param array{name: string, rows: array<array<string, mixed>>} $params
     * @return array{id: string, name: string, columns: array, row_count: int, updated_at: string}
     */
    public function createSource(array $params): array
    {
        return $this->post('/data/sources', $params);
    }

    /**
     * Append rows to an existing data source. Columns are union-merged.
     *
     * @param array{rows: array<array<string, mixed>>} $params
     */
    public function appendRows(string $sourceId, array $params): array
    {
        return $this->post('/data/sources/' . rawurlencode($sourceId) . '/rows', $params);
    }

    /**
     * Replace all data in a source.
     *
     * @param array{rows: array<array<string, mixed>>} $params
     */
    public function replaceData(string $sourceId, array $params): array
    {
        return $this->put('/data/sources/' . rawurlencode($sourceId) . '/data', $params);
    }

    /**
     * Delete a data source and all associated data, QDF, and FGA warrants.
     */
    public function deleteSource(string $sourceId): array
    {
        return $this->delete('/data/sources/' . rawurlencode($sourceId));
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
