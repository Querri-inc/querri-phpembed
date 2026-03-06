<?php

declare(strict_types=1);

namespace Querri\Embed;

use Querri\Embed\Http\HttpClient;
use Querri\Embed\Resources\ChatsResource;
use Querri\Embed\Resources\DashboardsResource;
use Querri\Embed\Resources\DataResource;
use Querri\Embed\Resources\ProjectsResource;
use Querri\Embed\Resources\SourcesResource;
use Querri\Embed\Session\GetSessionResult;

/**
 * User-scoped client that calls the internal API (/api/) with embed session auth.
 * Resources are FGA-filtered — only data the session user can access is returned.
 *
 * Create via QuerriClient::asUser($session).
 *
 * @property-read ProjectsResource $projects     Projects — list (FGA-filtered), retrieve, run, steps.
 * @property-read DashboardsResource $dashboards Dashboards — list (FGA-filtered), retrieve, refresh.
 * @property-read SourcesResource $sources       Sources & connectors — list (FGA-filtered), CRUD, sync.
 * @property-read DataResource $data             Data access — query sources with RLS.
 * @property-read ChatsResource $chats           Chats — CRUD within accessible projects.
 */
final class UserQuerriClient
{
    private readonly HttpClient $httpClient;

    private ?ProjectsResource $_projects = null;
    private ?DashboardsResource $_dashboards = null;
    private ?SourcesResource $_sources = null;
    private ?DataResource $_data = null;
    private ?ChatsResource $_chats = null;

    /**
     * @internal Use QuerriClient::asUser() to create instances.
     */
    public function __construct(GetSessionResult $session, Config $parentConfig)
    {
        // Derive host from parent config's baseUrl by stripping /api/v1
        $host = preg_replace('#/api/v1$#', '', $parentConfig->baseUrl);

        $config = Config::forSession(
            sessionToken: $session->sessionToken,
            host: $host,
            timeout: $parentConfig->timeout,
            maxRetries: $parentConfig->maxRetries,
        );

        $this->httpClient = new HttpClient($config);
    }

    public function __get(string $name): ProjectsResource|DashboardsResource|SourcesResource|DataResource|ChatsResource
    {
        return match ($name) {
            'projects' => $this->_projects ??= new ProjectsResource($this->httpClient),
            'dashboards' => $this->_dashboards ??= new DashboardsResource($this->httpClient),
            'sources' => $this->_sources ??= new SourcesResource($this->httpClient),
            'data' => $this->_data ??= new DataResource($this->httpClient),
            'chats' => $this->_chats ??= new ChatsResource($this->httpClient),
            default => throw new \Error("Undefined property: " . static::class . "::\$$name"),
        };
    }
}
