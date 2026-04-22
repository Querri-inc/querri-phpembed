<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Resources;

use Querri\Embed\Tests\Unit\MockHttpTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DataResourceTest extends MockHttpTestCase
{
    public function testListSourcesGetsWithPagination(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);

        $client->data->listSources(['limit' => 20]);

        $this->assertSame('GET', $this->recorded[0]['method']);
        $this->assertStringContainsString('/data/sources?limit=20', $this->recorded[0]['url']);
    }

    public function testGetSourceEncodesId(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->getSource('src/with/slash');

        $this->assertStringContainsString('src%2Fwith%2Fslash', $this->recorded[0]['url']);
    }

    public function testCreateSourcePostsRows(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->createSource([
            'name' => 'Users',
            'rows' => [['id' => 1], ['id' => 2]],
        ]);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/data/sources', $this->recorded[0]['url']);
        $this->assertStringContainsString('"rows":[{"id":1},{"id":2}]', $this->recorded[0]['body'] ?? '');
    }

    public function testAppendRowsPostsToRowsPath(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->appendRows('src_1', ['rows' => [['id' => 3]]]);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/data/sources/src_1/rows', $this->recorded[0]['url']);
    }

    public function testReplaceDataPutsToDataPath(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->replaceData('src_1', ['rows' => [['id' => 1]]]);

        $this->assertSame('PUT', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/data/sources/src_1/data', $this->recorded[0]['url']);
    }

    public function testDeleteSourceDeletes(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->deleteSource('src_1');

        $this->assertSame('DELETE', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/data/sources/src_1', $this->recorded[0]['url']);
    }

    public function testQueryPostsSqlAndSource(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->query(['sql' => 'SELECT 1', 'source_id' => 'src_1']);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/data/query', $this->recorded[0]['url']);
        $this->assertStringContainsString('"sql":"SELECT 1"', $this->recorded[0]['body'] ?? '');
    }

    public function testGetSourceDataGetsWithParams(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->getSourceData('src_1', ['page' => 2]);

        $this->assertSame('GET', $this->recorded[0]['method']);
        $this->assertStringContainsString('/data/sources/src_1/data?page=2', $this->recorded[0]['url']);
    }
}
