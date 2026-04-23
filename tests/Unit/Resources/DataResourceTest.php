<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Resources;

use Querri\Embed\Tests\Unit\MockHttpTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DataResourceTest extends MockHttpTestCase
{
    // ─── Primary (new) method names ────────────────────────────────

    public function testListGetsWithPagination(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);

        $client->data->list(['limit' => 20]);

        $this->assertSame('GET', $this->recorded[0]['method']);
        $this->assertStringContainsString('/data/sources?limit=20', $this->recorded[0]['url']);
    }

    public function testRetrieveEncodesId(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->retrieve('src/with/slash');

        $this->assertStringContainsString('src%2Fwith%2Fslash', $this->recorded[0]['url']);
    }

    public function testCreatePostsRows(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->create([
            'name' => 'Users',
            'rows' => [['id' => 1], ['id' => 2]],
        ]);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/data/sources', $this->recorded[0]['url']);
        $this->assertStringContainsString('"rows":[{"id":1},{"id":2}]', $this->recorded[0]['body'] ?? '');
    }

    public function testDelDeletes(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->del('src_1');

        $this->assertSame('DELETE', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/data/sources/src_1', $this->recorded[0]['url']);
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

    // ─── Deprecated aliases still work (one smoke test each) ────────

    public function testListSourcesDeprecatedAliasStillWorks(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);
        /** @phpstan-ignore method.deprecated (deprecated alias — verified still functional) */
        $client->data->listSources(['limit' => 5]);
        $this->assertStringContainsString('/data/sources?limit=5', $this->recorded[0]['url']);
    }

    public function testGetSourceDeprecatedAliasStillWorks(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        /** @phpstan-ignore method.deprecated (deprecated alias — verified still functional) */
        $client->data->getSource('src_1');
        $this->assertStringEndsWith('/data/sources/src_1', $this->recorded[0]['url']);
    }

    public function testCreateSourceDeprecatedAliasStillWorks(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        /** @phpstan-ignore method.deprecated (deprecated alias — verified still functional) */
        $client->data->createSource(['name' => 'X', 'rows' => []]);
        $this->assertSame('POST', $this->recorded[0]['method']);
    }

    public function testDeleteSourceDeprecatedAliasStillWorks(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        /** @phpstan-ignore method.deprecated (deprecated alias — verified still functional) */
        $client->data->deleteSource('src_1');
        $this->assertSame('DELETE', $this->recorded[0]['method']);
    }
}
