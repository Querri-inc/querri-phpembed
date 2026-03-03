<?php

declare(strict_types=1);

namespace Querri\Embed;

use Querri\Embed\Http\HttpClient;
use Querri\Embed\Resources\UsersResource;
use Querri\Embed\Resources\EmbedResource;
use Querri\Embed\Resources\PoliciesResource;
use Querri\Embed\Session\GetSession;
use Querri\Embed\Session\GetSessionResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class QuerriClient
{
    private readonly HttpClient $httpClient;

    private ?UsersResource $_users = null;
    private ?EmbedResource $_embed = null;
    private ?PoliciesResource $_policies = null;

    /**
     * Create a new Querri client.
     *
     * @param string|array{api_key?: string, apiKey?: string, org_id?: string, orgId?: string, host?: string, timeout?: float, max_retries?: int, maxRetries?: int}|Config $config
     *   - Pass an API key string: new QuerriClient('qk_...')
     *   - Pass a config array: new QuerriClient(['api_key' => 'qk_...', 'org_id' => 'org_...'])
     *   - Pass a Config object: new QuerriClient(Config::resolve(apiKey: 'qk_...'))
     *   - Pass nothing to read from environment variables: new QuerriClient()
     * @param HttpClientInterface|null $httpClient Optional Symfony HttpClient for testing/custom transport
     */
    public function __construct(string|array|Config $config = [], ?HttpClientInterface $httpClient = null)
    {
        $resolved = match (true) {
            is_string($config) => Config::resolve(apiKey: $config),
            is_array($config) => Config::resolve(
                apiKey: $config['apiKey'] ?? $config['api_key'] ?? null,
                orgId: $config['orgId'] ?? $config['org_id'] ?? null,
                host: $config['host'] ?? null,
                timeout: isset($config['timeout']) ? (float) $config['timeout'] : null,
                maxRetries: ($mr = $config['maxRetries'] ?? $config['max_retries'] ?? null) !== null
                    ? (int) $mr
                    : null,
            ),
            $config instanceof Config => $config,
        };

        $this->httpClient = new HttpClient($resolved, $httpClient);
    }

    /**
     * Users resource — create, retrieve, list, update, delete, and getOrCreate users.
     */
    public UsersResource $users {
        get => $this->_users ??= new UsersResource($this->httpClient);
    }

    /**
     * Embed resource — create, refresh, list, and revoke embed sessions.
     */
    public EmbedResource $embed {
        get => $this->_embed ??= new EmbedResource($this->httpClient);
    }

    /**
     * Policies resource — create, retrieve, list, update, delete policies and manage user assignments.
     */
    public PoliciesResource $policies {
        get => $this->_policies ??= new PoliciesResource($this->httpClient);
    }

    /**
     * Convenience method: resolve user, ensure access policy, create embed session.
     *
     * This is the flagship method for the embed use case. It orchestrates
     * three API calls into a single method:
     *
     * 1. User resolution — calls users.getOrCreate() with the external ID
     * 2. Access policy — auto-creates or reuses a deterministically-named policy
     * 3. Session creation — creates an embed session token
     *
     * @param array{
     *   user: string|array{external_id: string, email?: string, first_name?: string, last_name?: string, role?: string},
     *   access?: array{policy_ids: string[]}|array{sources: string[], filters: array<string, string|string[]>}|null,
     *   origin?: string|null,
     *   ttl?: int,
     * } $params
     */
    public function getSession(array $params): GetSessionResult
    {
        return GetSession::execute($this, $params);
    }
}
