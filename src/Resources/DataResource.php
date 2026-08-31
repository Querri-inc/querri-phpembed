<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Data API — create and query data sources with automatic RLS enforcement.
 *
 * All endpoints live under /sources: the server's standalone /data/* routes
 * were merged into the sources router, so this resource and SourcesResource
 * address the same objects — this one focuses on row-level data access.
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
        return $this->get('/sources', $params);
    }

    /**
     * Retrieve a data source by ID.
     *
     * @return array<string, mixed>
     */
    public function retrieve(string $sourceId): array
    {
        return $this->get('/sources/' . rawurlencode($sourceId));
    }

    /**
     * Create a data source with inline JSON rows.
     *
     * @param array{name: string, rows: list<array<string, mixed>>} $params
     *   rows must contain at least one row object.
     * @return array{id: string, name: string, columns: array<int, string>, row_count: int, updated_at: string}
     */
    public function create(array $params): array
    {
        return $this->post('/sources', $params);
    }

    /**
     * Delete a data source and all associated data, QDF, and FGA warrants.
     *
     * @return array<string, mixed>
     */
    public function del(string $sourceId): array
    {
        return $this->delete('/sources/' . rawurlencode($sourceId));
    }

    /**
     * Append rows to an existing data source. Columns are union-merged.
     *
     * @param array{rows: list<array<string, mixed>>} $params
     * @return array<string, mixed>
     */
    public function appendRows(string $sourceId, array $params): array
    {
        return $this->post('/sources/' . rawurlencode($sourceId) . '/rows', $params);
    }

    /**
     * Replace all data in a source.
     *
     * @param array{rows: list<array<string, mixed>>} $params
     * @return array<string, mixed>
     */
    public function replaceData(string $sourceId, array $params): array
    {
        return $this->put('/sources/' . rawurlencode($sourceId) . '/data', $params);
    }

    /**
     * Execute a SQL query against a data source.
     *
     * The source ID is part of the path (POST /sources/{source_id}/query);
     * the body carries the SQL and pagination only.
     *
     * @param array{sql: string, page?: int, page_size?: int} $params
     * @return array{data: array<int, array<string, mixed>>, total_rows: int, page: int, page_size: int}
     */
    public function query(string $sourceId, array $params): array
    {
        return $this->post('/sources/' . rawurlencode($sourceId) . '/query', $params);
    }

    /**
     * Get paginated data from a source.
     *
     * @param array{page?: int, page_size?: int}|null $params
     * @return array<string, mixed>
     */
    public function getSourceData(string $sourceId, ?array $params = null): array
    {
        return $this->get('/sources/' . rawurlencode($sourceId) . '/data', $params);
    }

    // ─── Deprecated aliases ─────────────────────────────────────────

    /**
     * @deprecated since 0.2.0; will be removed in the next major release. Use list() instead.
     * @param array{limit?: int, after?: string}|null $params
     * @return array{data: array<int, array<string, mixed>>, has_more: bool, next_cursor: string|null}
     */
    public function listSources(?array $params = null): array
    {
        return $this->list($params);
    }

    /**
     * @deprecated since 0.2.0; will be removed in the next major release. Use retrieve() instead.
     * @return array<string, mixed>
     */
    public function getSource(string $sourceId): array
    {
        return $this->retrieve($sourceId);
    }

    /**
     * @deprecated since 0.2.0; will be removed in the next major release. Use create() instead.
     * @param array{name: string, rows: list<array<string, mixed>>} $params
     * @return array{id: string, name: string, columns: array<int, string>, row_count: int, updated_at: string}
     */
    public function createSource(array $params): array
    {
        return $this->create($params);
    }

    /**
     * @deprecated since 0.2.0; will be removed in the next major release. Use del() instead.
     * @return array<string, mixed>
     */
    public function deleteSource(string $sourceId): array
    {
        return $this->del($sourceId);
    }
}
