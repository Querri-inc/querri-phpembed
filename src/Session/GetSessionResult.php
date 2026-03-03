<?php

declare(strict_types=1);

namespace Querri\Embed\Session;

/**
 * Immutable result from getSession(). Implements JsonSerializable so it can
 * be passed directly to json_encode() for API response output.
 */
final readonly class GetSessionResult implements \JsonSerializable
{
    public function __construct(
        public string $sessionToken,
        public int $expiresIn,
        public string $userId,
        public ?string $externalId = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'session_token' => $this->sessionToken,
            'expires_in' => $this->expiresIn,
            'user_id' => $this->userId,
            'external_id' => $this->externalId,
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
