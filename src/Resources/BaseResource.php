<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

use Querri\Embed\Http\HttpClient;

/**
 * Abstract base for API resource classes. Provides HTTP method helpers
 * (get, post, put, patch, delete) that delegate to HttpClient.
 */
abstract class BaseResource
{
    public function __construct(
        protected readonly HttpClient $client,
    ) {
    }

    protected function get(string $path, ?array $query = null): array
    {
        return $this->client->request([
            'method' => 'GET',
            'path' => $path,
            'query' => $query,
        ]);
    }

    protected function post(string $path, ?array $body = null): array
    {
        return $this->client->request([
            'method' => 'POST',
            'path' => $path,
            'body' => $body,
        ]);
    }

    protected function put(string $path, ?array $body = null): array
    {
        return $this->client->request([
            'method' => 'PUT',
            'path' => $path,
            'body' => $body,
        ]);
    }

    protected function patch(string $path, ?array $body = null): array
    {
        return $this->client->request([
            'method' => 'PATCH',
            'path' => $path,
            'body' => $body,
        ]);
    }

    protected function delete(string $path): array
    {
        return $this->client->request([
            'method' => 'DELETE',
            'path' => $path,
        ]);
    }
}
