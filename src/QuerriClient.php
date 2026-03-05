<?php

declare(strict_types=1);

namespace Querri\Embed;

use Querri\Embed\Http\HttpClient;
use Querri\Embed\Resources\AuditResource;
use Querri\Embed\Resources\ChatsResource;
use Querri\Embed\Resources\DashboardsResource;
use Querri\Embed\Resources\DataResource;
use Querri\Embed\Resources\EmbedResource;
use Querri\Embed\Resources\FilesResource;
use Querri\Embed\Resources\KeysResource;
use Querri\Embed\Resources\PoliciesResource;
use Querri\Embed\Resources\ProjectsResource;
use Querri\Embed\Resources\SharingResource;
use Querri\Embed\Resources\SourcesResource;
use Querri\Embed\Resources\UsageResource;
use Querri\Embed\Resources\UsersResource;
use Querri\Embed\Session\GetSession;
use Querri\Embed\Session\GetSessionResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @property-read UsersResource $users          User management — CRUD, getOrCreate, external ID mapping.
 * @property-read EmbedResource $embed          Embed sessions — create, refresh, list, revoke.
 * @property-read PoliciesResource $policies    Access policies / RLS — CRUD, user assignment, resolve.
 * @property-read DashboardsResource $dashboards Dashboard management — CRUD, refresh.
 * @property-read ProjectsResource $projects    Projects — CRUD, run, steps.
 * @property-read ChatsResource $chats          Chats — CRUD within projects.
 * @property-read DataResource $data            Data access — query sources with RLS.
 * @property-read SourcesResource $sources      Sources & connectors — CRUD, sync.
 * @property-read FilesResource $files          File management — list, retrieve, delete.
 * @property-read KeysResource $keys            API key management — create, list, revoke.
 * @property-read AuditResource $audit          Audit log — query events.
 * @property-read UsageResource $usage          Usage metrics — org and per-user.
 * @property-read SharingResource $sharing      Sharing / permissions — project and dashboard access.
 */
final class QuerriClient
{
    private readonly HttpClient $httpClient;

    private ?UsersResource $_users = null;
    private ?EmbedResource $_embed = null;
    private ?PoliciesResource $_policies = null;
    private ?DashboardsResource $_dashboards = null;
    private ?ProjectsResource $_projects = null;
    private ?ChatsResource $_chats = null;
    private ?DataResource $_data = null;
    private ?SourcesResource $_sources = null;
    private ?FilesResource $_files = null;
    private ?KeysResource $_keys = null;
    private ?AuditResource $_audit = null;
    private ?UsageResource $_usage = null;
    private ?SharingResource $_sharing = null;

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
     * @internal Lazy-load resource sub-clients.
     */
    public function __get(string $name): UsersResource|EmbedResource|PoliciesResource|DashboardsResource|ProjectsResource|ChatsResource|DataResource|SourcesResource|FilesResource|KeysResource|AuditResource|UsageResource|SharingResource
    {
        return match ($name) {
            'users' => $this->_users ??= new UsersResource($this->httpClient),
            'embed' => $this->_embed ??= new EmbedResource($this->httpClient),
            'policies' => $this->_policies ??= new PoliciesResource($this->httpClient),
            'dashboards' => $this->_dashboards ??= new DashboardsResource($this->httpClient),
            'projects' => $this->_projects ??= new ProjectsResource($this->httpClient),
            'chats' => $this->_chats ??= new ChatsResource($this->httpClient),
            'data' => $this->_data ??= new DataResource($this->httpClient),
            'sources' => $this->_sources ??= new SourcesResource($this->httpClient),
            'files' => $this->_files ??= new FilesResource($this->httpClient),
            'keys' => $this->_keys ??= new KeysResource($this->httpClient),
            'audit' => $this->_audit ??= new AuditResource($this->httpClient),
            'usage' => $this->_usage ??= new UsageResource($this->httpClient),
            'sharing' => $this->_sharing ??= new SharingResource($this->httpClient),
            default => throw new \Error("Undefined property: " . static::class . "::\$$name"),
        };
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
