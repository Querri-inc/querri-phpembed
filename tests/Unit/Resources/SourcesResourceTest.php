<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Resources;

use Querri\Embed\Tests\Unit\MockHttpTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SourcesResourceTest extends MockHttpTestCase
{
    public function testListConnectorsGets(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);
        $client->sources->listConnectors();
        $this->assertStringEndsWith('/connectors', $this->recorded[0]['url']);
    }

    public function testListGets(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);
        $client->sources->list();
        $this->assertStringEndsWith('/sources', $this->recorded[0]['url']);
    }

    public function testCreatePosts(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->sources->create([
            'name' => 'S',
            'connector_id' => 'c_1',
            'config' => ['url' => 'x'],
        ]);
        $this->assertSame('POST', $this->recorded[0]['method']);
    }

    public function testUpdatePatches(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->sources->update('s_1', ['name' => 'x']);
        $this->assertSame('PATCH', $this->recorded[0]['method']);
    }

    public function testDelDeletes(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->sources->del('s_1');
        $this->assertSame('DELETE', $this->recorded[0]['method']);
    }

    public function testSyncPosts(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->sources->sync('s_1');
        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/sources/s_1/sync', $this->recorded[0]['url']);
    }
}
