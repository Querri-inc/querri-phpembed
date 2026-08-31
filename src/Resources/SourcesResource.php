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
     * Create a data source with inline JSON rows.
     *
     * The server binds POST /sources to {name, rows} (rows must contain at
     * least one row object) — there is no connector-based create on this
     * endpoint.
     *
     * @param array{name: string, rows: list<array<string, mixed>>} $params
     * @return array{id: string, name: string, columns: array<int, string>, row_count: int, updated_at: string}
     */
    public function create(array $params): array
    {
        return $this->post('/sources', $params);
    }

    /**
     * Update source metadata and configuration.
     *
     * @param array{name?: string, description?: string, config?: array<string, mixed>, access_controlled?: bool} $params
     *   access_controlled: when true, users without a matching access policy
     *   see zero rows (fail-closed).
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
