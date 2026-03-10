<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Files API — list, retrieve, and delete uploaded files.
 *
 * Note: File upload requires multipart form data which is handled
 * via a dedicated upload() method using cURL directly.
 */
final class FilesResource extends BaseResource
{
    /**
     * @param array{limit?: int, after?: string}|null $params
     */
    public function list(?array $params = null): array
    {
        return $this->get('/files', $params);
    }

    public function retrieve(string $fileId): array
    {
        return $this->get('/files/' . rawurlencode($fileId));
    }

    public function del(string $fileId): array
    {
        return $this->delete('/files/' . rawurlencode($fileId));
    }
}
