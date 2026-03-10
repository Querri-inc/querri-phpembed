<?php

declare(strict_types=1);

namespace Querri\Embed\Resources;

/**
 * Audit Log API — query audit events.
 */
final class AuditResource extends BaseResource
{
    /**
     * @param array{actor_id?: string, target_id?: string, action?: string, start_date?: string, end_date?: string, limit?: int, after?: string} $params
     */
    public function listEvents(?array $params = null): array
    {
        return $this->get('/audit/events', $params);
    }
}
