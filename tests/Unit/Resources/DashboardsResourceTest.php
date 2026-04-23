<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Resources;

use Querri\Embed\Tests\Unit\MockHttpTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DashboardsResourceTest extends MockHttpTestCase
{
    public function testListGetsWithUserFilter(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);

        $client->dashboards->list(['user_id' => 'u_1']);

        $this->assertStringContainsString('/dashboards?user_id=u_1', $this->recorded[0]['url']);
    }

    public function testCreatePosts(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->dashboards->create(['name' => 'Q1 Dash']);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertSame('{"name":"Q1 Dash"}', $this->recorded[0]['body']);
    }

    public function testRetrieveEncodesId(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->dashboards->retrieve('d/slash');
        $this->assertStringContainsString('d%2Fslash', $this->recorded[0]['url']);
    }

    public function testUpdatePatches(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->dashboards->update('d_1', ['name' => 'x']);
        $this->assertSame('PATCH', $this->recorded[0]['method']);
    }

    public function testDelDeletes(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->dashboards->del('d_1');
        $this->assertSame('DELETE', $this->recorded[0]['method']);
    }

    public function testRefreshPosts(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->dashboards->refresh('d_1');
        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/dashboards/d_1/refresh', $this->recorded[0]['url']);
    }

    public function testRefreshStatusGets(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->dashboards->refreshStatus('d_1');
        $this->assertSame('GET', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/dashboards/d_1/refresh/status', $this->recorded[0]['url']);
    }
}
