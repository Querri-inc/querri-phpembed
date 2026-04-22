<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Data API — query data sources with automatic RLS enforcement.
 */
final class DataResource extends BaseResource
{
    /**
     * List data sources.
     *
     * @param array{limit?: int, after?: string}|null $params
     * @return array{data: array<int, array<string, mixed>>, has_more: bool, next_cursor: string|null}
     */
    public function list(?array $params = null): array
    {
        return $this->get('/data/sources', $params);
    }

    /**
     * Retrieve a data source by ID.
     *
     * @return array<string, mixed>
     */
    public function retrieve(string $sourceId): array
    {
        return $this->get('/data/sources/' . rawurlencode($sourceId));
    }

    /**
     * Create a data source with inline JSON rows.
     *
     * @param array{name: string, rows: array<array<string, mixed>>} $params
     * @return array{id: string, name: string, columns: array<int, array<string, mixed>>, row_count: int, updated_at: string}
     */
    public function create(array $params): array
    {
        return $this->post('/data/sources', $params);
    }

    /**
     * Delete a data source and all associated data, QDF, and FGA warrants.
     *
     * @return array<string, mixed>
     */
    public function del(string $sourceId): array
    {
        return $this->delete('/data/sources/' . rawurlencode($sourceId));
    }

    /**
     * Append rows to an existing data source. Columns are union-merged.
     *
     * @param array{rows: array<array<string, mixed>>} $params
     * @return array<string, mixed>
     */
    public function appendRows(string $sourceId, array $params): array
    {
        return $this->post('/data/sources/' . rawurlencode($sourceId) . '/rows', $params);
    }

    /**
     * Replace all data in a source.
     *
     * @param array{rows: array<array<string, mixed>>} $params
     * @return array<string, mixed>
     */
    public function replaceData(string $sourceId, array $params): array
    {
        return $this->put('/data/sources/' . rawurlencode($sourceId) . '/data', $params);
    }

    /**
     * Execute a SQL query against a data source.
     *
     * @param array{sql: string, source_id: string, page?: int, page_size?: int} $params
     * @return array<string, mixed>
     */
    public function query(array $params): array
    {
        return $this->post('/data/query', $params);
    }

    /**
     * Get paginated data from a source.
     *
     * @param array<string, mixed>|null $params
     * @return array<string, mixed>
     */
    public function getSourceData(string $sourceId, ?array $params = null): array
    {
        return $this->get('/data/sources/' . rawurlencode($sourceId) . '/data', $params);
    }

    // ─── Deprecated aliases (removed in 0.3.0) ──────────────────────

    /**
     * @deprecated since 0.2.0, removed in 0.3.0. Use list() instead.
     * @param array{limit?: int, after?: string}|null $params
     * @return array{data: array<int, array<string, mixed>>, has_more: bool, next_cursor: string|null}
     */
    public function listSources(?array $params = null): array
    {
        return $this->list($params);
    }

    /**
     * @deprecated since 0.2.0, removed in 0.3.0. Use retrieve() instead.
     * @return array<string, mixed>
     */
    public function getSource(string $sourceId): array
    {
        return $this->retrieve($sourceId);
    }

    /**
     * @deprecated since 0.2.0, removed in 0.3.0. Use create() instead.
     * @param array{name: string, rows: array<array<string, mixed>>} $params
     * @return array{id: string, name: string, columns: array<int, array<string, mixed>>, row_count: int, updated_at: string}
     */
    public function createSource(array $params): array
    {
        return $this->create($params);
    }

    /**
     * @deprecated since 0.2.0, removed in 0.3.0. Use del() instead.
     * @return array<string, mixed>
     */
    public function deleteSource(string $sourceId): array
    {
        return $this->del($sourceId);
    }
}
