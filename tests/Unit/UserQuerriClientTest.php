<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Querri\Embed\Config;
use Querri\Embed\Resources\ChatsResource;
use Querri\Embed\Resources\DashboardsResource;
use Querri\Embed\Resources\DataResource;
use Querri\Embed\Resources\ProjectsResource;
use Querri\Embed\Resources\SourcesResource;
use Querri\Embed\Session\GetSessionResult;
use Querri\Embed\UserQuerriClient;

/**
 * UserQuerriClient constructs its own HttpClient from the session token; the
 * internal transport isn't injectable, so these tests focus on the
 * construction + lazy-load contract rather than HTTP behavior.
 */
final class UserQuerriClientTest extends TestCase
{
    private function makeClient(): UserQuerriClient
    {
        $session = new GetSessionResult(
            sessionToken: 'tok_abc',
            expiresIn: 3600,
            userId: 'u_1',
            externalId: 'ext_99',
        );
        $parentConfig = Config::resolve(
            apiKey: 'qk_parent',
            host: 'https://example.com',
        );
        return new UserQuerriClient($session, $parentConfig);
    }

    /** @return array<string, array{string, class-string}> */
    public static function resourceCases(): array
    {
        return [
            'projects' => ['projects', ProjectsResource::class],
            'dashboards' => ['dashboards', DashboardsResource::class],
            'sources' => ['sources', SourcesResource::class],
            'data' => ['data', DataResource::class],
            'chats' => ['chats', ChatsResource::class],
        ];
    }

    /**
     * @param class-string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('resourceCases')]
    public function testLazyLoadsFgaFilteredResources(string $property, string $expected): void
    {
        $client = $this->makeClient();
        $this->assertInstanceOf($expected, $client->$property);
    }

    public function testLazyLoadReturnsSameInstanceOnRepeat(): void
    {
        $client = $this->makeClient();
        $this->assertSame($client->projects, $client->projects);
    }

    public function testAdminResourcesAreNotExposed(): void
    {
        // UserQuerriClient deliberately excludes admin/org resources (users,
        // embed, policies, keys, audit, usage, sharing) — only FGA-filtered
        // end-user resources are reachable.
        $client = $this->makeClient();
        $this->expectException(\Error::class);
        /** @phpstan-ignore property.notFound (testing the deliberate exclusion) */
        $_ = $client->users;
    }
}
