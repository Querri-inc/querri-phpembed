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
    public function list(): array
    {
        return $this->get('/files');
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
