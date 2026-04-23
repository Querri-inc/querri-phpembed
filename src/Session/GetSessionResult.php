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

    /**
     * @return array{session_token: string, expires_in: int, user_id: string, external_id: string|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'session_token' => $this->sessionToken,
            'expires_in' => $this->expiresIn,
            'user_id' => $this->userId,
            'external_id' => $this->externalId,
        ];
    }

    /**
     * @deprecated since 0.2.0, removed in 0.3.0. Use jsonSerialize() directly,
     *   or pass the object to json_encode() — JsonSerializable handles the
     *   conversion automatically.
     * @return array{session_token: string, expires_in: int, user_id: string, external_id: string|null}
     */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
