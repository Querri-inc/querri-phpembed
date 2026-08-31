<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Querri\Embed\Config;
use Querri\Embed\Resources\ChatsResource;
use Querri\Embed\Resources\DataResource;
use Querri\Embed\Resources\ProjectsResource;
use Querri\Embed\Resources\SourcesResource;
use Querri\Embed\Resources\UserDashboardsResource;
use Querri\Embed\Session\GetSessionResult;
use Querri\Embed\UserQuerriClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class UserQuerriClientTest extends MockHttpTestCase
{
    private function makeSession(): GetSessionResult
    {
        return new GetSessionResult(
            sessionToken: 'tok_abc',
            expiresIn: 3600,
            userId: 'u_1',
            externalId: 'ext_99',
        );
    }

    private function makeParentConfig(): Config
    {
        return Config::resolve(
            apiKey: 'qk_parent',
            orgId: 'org_parent',
            host: 'https://example.com',
        );
    }

    private function makeInjectedUserClient(MockResponse|\Throwable ...$responses): UserQuerriClient
    {
        return new UserQuerriClient(
            $this->makeSession(),
            $this->makeParentConfig(),
            $this->makeMockTransport(array_values($responses)),
        );
    }

    // ─── Construction + lazy loading ────────────────────────────────

    /** @return array<string, array{string, class-string}> */
    public static function resourceCases(): array
    {
        return [
            'projects' => ['projects', ProjectsResource::class],
            'dashboards' => ['dashboards', UserDashboardsResource::class],
            'sources' => ['sources', SourcesResource::class],
            'data' => ['data', DataResource::class],
            'chats' => ['chats', ChatsResource::class],
        ];
    }

    /**
     * @param class-string $expected
     */
    #[DataProvider('resourceCases')]
    public function testLazyLoadsFgaFilteredResources(string $property, string $expected): void
    {
        $client = new UserQuerriClient($this->makeSession(), $this->makeParentConfig());
        $this->assertInstanceOf($expected, $client->$property);
    }

    public function testLazyLoadReturnsSameInstanceOnRepeat(): void
    {
        $client = new UserQuerriClient($this->makeSession(), $this->makeParentConfig());
        $this->assertSame($client->projects, $client->projects);
    }

    public function testAdminResourcesAreNotExposed(): void
    {
        // UserQuerriClient deliberately excludes admin/org resources (users,
        // embed, policies, keys, audit, usage, sharing) — only FGA-filtered
        // end-user resources are reachable.
        $client = new UserQuerriClient($this->makeSession(), $this->makeParentConfig());
        $this->expectException(\Error::class);
        /** @phpstan-ignore property.notFound (testing the deliberate exclusion) */
        $_ = $client->users;
    }

    // ─── HTTP behavior (via injected transport) ─────────────────────

    public function testRequestsUsePublicV1BaseUrl(): void
    {
        $client = $this->makeInjectedUserClient(
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        );

        $client->projects->list();

        // UserQuerriClient targets the public /api/v1 API — X-Embed-Session
        // is the highest-priority credential there.
        $this->assertStringStartsWith('https://example.com/api/v1/', $this->recorded[0]['url']);
        $this->assertStringEndsWith('/projects', $this->recorded[0]['url']);
    }

    public function testDashboardsSurfaceIsReadOnly(): void
    {
        // Embed sessions lack the admin:dashboards:write scope server-side;
        // the user-scoped surface must not offer the write methods at all.
        $client = new UserQuerriClient($this->makeSession(), $this->makeParentConfig());
        $dashboards = $client->dashboards;

        $this->assertInstanceOf(UserDashboardsResource::class, $dashboards);
        foreach (['create', 'update', 'del', 'refresh'] as $writeMethod) {
            $this->assertFalse(
                method_exists($dashboards, $writeMethod),
                "UserDashboardsResource must not expose write method {$writeMethod}()",
            );
        }
        $this->assertTrue(method_exists($dashboards, 'list'));
        $this->assertTrue(method_exists($dashboards, 'retrieve'));
        $this->assertTrue(method_exists($dashboards, 'refreshStatus'));
    }

    public function testSendsEmbedSessionHeaderNotBearer(): void
    {
        $client = $this->makeInjectedUserClient(
            new MockResponse('{}', ['http_code' => 200]),
        );

        $client->dashboards->retrieve('d_1');

        $headers = $this->recorded[0]['headers'];
        $this->assertSame('tok_abc', $headers['x-embed-session'] ?? null);
        $this->assertArrayNotHasKey('authorization', $headers);
        $this->assertArrayNotHasKey('x-tenant-id', $headers);
    }

    public function testEndToEndListRetrieveCycle(): void
    {
        $client = $this->makeInjectedUserClient(
            new MockResponse(
                '{"data":[{"id":"p_1","name":"Q1"}],"has_more":false,"next_cursor":null}',
                ['http_code' => 200],
            ),
            new MockResponse('{"id":"p_1","name":"Q1","steps":[]}', ['http_code' => 200]),
        );

        $listResponse = $client->projects->list();
        $project = $client->projects->retrieve($listResponse['data'][0]['id']);

        $this->assertSame('Q1', $project['name']);
        $this->assertCount(2, $this->recorded);
        $this->assertSame('GET', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/projects', $this->recorded[0]['url']);
        $this->assertStringEndsWith('/projects/p_1', $this->recorded[1]['url']);
    }

    public function testHostIsDerivedFromParentConfigNotRegex(): void
    {
        $session = new GetSessionResult('t', 10, 'u');
        $parent = Config::resolve(
            apiKey: 'qk',
            orgId: 'org_x',
            host: 'https://custom.example.com/api/v1',
        );

        $client = new UserQuerriClient(
            $session,
            $parent,
            $this->makeMockTransport([new MockResponse('{}', ['http_code' => 200])]),
        );

        $client->data->list();

        // Parent's /api/v1 suffix is stripped at Config level to a bare host;
        // the user client then re-appends /api/v1 without doubling it.
        $this->assertStringStartsWith('https://custom.example.com/api/v1/', $this->recorded[0]['url']);
        $this->assertStringNotContainsString('/api/v1/api', $this->recorded[0]['url']);
    }
}
