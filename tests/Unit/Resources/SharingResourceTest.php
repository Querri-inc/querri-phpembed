<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Resources;

use Querri\Embed\Tests\Unit\MockHttpTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SharingResourceTest extends MockHttpTestCase
{
    public function testShareProjectPosts(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->sharing->shareProject('p_1', ['user_id' => 'u_1', 'permission' => 'view']);
        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/projects/p_1/shares', $this->recorded[0]['url']);
    }

    public function testRevokeProjectShareDeletes(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->sharing->revokeProjectShare('p_1', 'u_1');
        $this->assertSame('DELETE', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/projects/p_1/shares/u_1', $this->recorded[0]['url']);
    }

    public function testListProjectSharesGets(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->sharing->listProjectShares('p_1');
        $this->assertStringEndsWith('/projects/p_1/shares', $this->recorded[0]['url']);
    }

    public function testShareDashboardPosts(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->sharing->shareDashboard('d_1', ['user_id' => 'u_1']);
        $this->assertStringEndsWith('/dashboards/d_1/shares', $this->recorded[0]['url']);
    }

    public function testRevokeDashboardShareDeletes(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->sharing->revokeDashboardShare('d_1', 'u_1');
        $this->assertSame('DELETE', $this->recorded[0]['method']);
    }

    public function testListDashboardSharesGets(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->sharing->listDashboardShares('d_1');
        $this->assertStringEndsWith('/dashboards/d_1/shares', $this->recorded[0]['url']);
    }

    public function testShareSourcePosts(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->sharing->shareSource('s_1', ['user_id' => 'u_1']);
        $this->assertStringEndsWith('/sources/s_1/shares', $this->recorded[0]['url']);
    }

    public function testOrgShareSourcePostsToHyphenatedPath(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->sharing->orgShareSource('s_1', ['enabled' => true]);
        $this->assertStringEndsWith('/sources/s_1/org-share', $this->recorded[0]['url']);
    }
}
