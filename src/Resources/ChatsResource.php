<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Chats API — create, list, retrieve, and delete chats within projects.
 *
 * Note: Stream endpoints (SSE) are not supported in the synchronous PHP SDK.
 * Use the REST API directly for streaming chat responses.
 */
final class ChatsResource extends BaseResource
{
    /**
     * @param array{name?: string} $params
     * @return array<string, mixed>
     */
    public function create(string $projectId, array $params = []): array
    {
        return $this->post(
            '/projects/' . rawurlencode($projectId) . '/chats',
            $params ?: null,
        );
    }

    /**
     * @param array{limit?: int, after?: string}|null $params
     * @return array{data: array<int, array<string, mixed>>, has_more: bool, next_cursor: string|null}
     */
    public function list(string $projectId, ?array $params = null): array
    {
        return $this->get('/projects/' . rawurlencode($projectId) . '/chats', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieve(string $projectId, string $chatId): array
    {
        return $this->get(
            '/projects/' . rawurlencode($projectId) . '/chats/' . rawurlencode($chatId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function del(string $projectId, string $chatId): array
    {
        return $this->delete(
            '/projects/' . rawurlencode($projectId) . '/chats/' . rawurlencode($chatId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(string $projectId, string $chatId): array
    {
        return $this->post(
            '/projects/' . rawurlencode($projectId) . '/chats/' . rawurlencode($chatId) . '/cancel',
        );
    }
}
