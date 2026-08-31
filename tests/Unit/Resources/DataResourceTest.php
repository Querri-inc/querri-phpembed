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
        $this->assertStringContainsString('/sources?limit=20', $this->recorded[0]['url']);
    }

    public function testRetrieveEncodesId(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->retrieve('src/with/slash');

        $this->assertStringEndsWith('/sources/src%2Fwith%2Fslash', $this->recorded[0]['url']);
    }

    public function testCreatePostsRows(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->create([
            'name' => 'Users',
            'rows' => [['id' => 1], ['id' => 2]],
        ]);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/sources', $this->recorded[0]['url']);
        $this->assertStringContainsString('"name":"Users"', $this->recorded[0]['body'] ?? '');
        $this->assertStringContainsString('"rows":[{"id":1},{"id":2}]', $this->recorded[0]['body'] ?? '');
    }

    public function testDelDeletes(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->del('src_1');

        $this->assertSame('DELETE', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/sources/src_1', $this->recorded[0]['url']);
    }

    public function testAppendRowsPostsToRowsPath(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->appendRows('src_1', ['rows' => [['id' => 3]]]);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/sources/src_1/rows', $this->recorded[0]['url']);
        $this->assertSame('{"rows":[{"id":3}]}', $this->recorded[0]['body']);
    }

    public function testReplaceDataPutsToDataPath(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->replaceData('src_1', ['rows' => [['id' => 1]]]);

        $this->assertSame('PUT', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/sources/src_1/data', $this->recorded[0]['url']);
    }

    public function testQueryPostsSqlWithSourceIdInPath(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"total_rows":0,"page":1,"page_size":100}', ['http_code' => 200]),
        ]);

        $client->data->query('src_1', ['sql' => 'SELECT 1', 'page' => 2, 'page_size' => 50]);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/sources/src_1/query', $this->recorded[0]['url']);
        $this->assertSame('{"sql":"SELECT 1","page":2,"page_size":50}', $this->recorded[0]['body']);
    }

    public function testQueryEncodesSourceId(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->query('src/with/slash', ['sql' => 'SELECT 1']);

        $this->assertStringEndsWith('/sources/src%2Fwith%2Fslash/query', $this->recorded[0]['url']);
    }

    public function testGetSourceDataGetsWithParams(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->data->getSourceData('src_1', ['page' => 2]);

        $this->assertSame('GET', $this->recorded[0]['method']);
        $this->assertStringContainsString('/sources/src_1/data?page=2', $this->recorded[0]['url']);
    }

    // ─── Deprecated aliases still work (one smoke test each) ────────

    public function testListSourcesDeprecatedAliasStillWorks(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);
        /** @phpstan-ignore method.deprecated (deprecated alias — verified still functional) */
        $client->data->listSources(['limit' => 5]);
        $this->assertStringContainsString('/sources?limit=5', $this->recorded[0]['url']);
    }

    public function testGetSourceDeprecatedAliasStillWorks(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        /** @phpstan-ignore method.deprecated (deprecated alias — verified still functional) */
        $client->data->getSource('src_1');
        $this->assertStringEndsWith('/sources/src_1', $this->recorded[0]['url']);
    }

    public function testCreateSourceDeprecatedAliasStillWorks(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        /** @phpstan-ignore method.deprecated (deprecated alias — verified still functional) */
        $client->data->createSource(['name' => 'X', 'rows' => [['a' => 1]]]);
        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/sources', $this->recorded[0]['url']);
    }

    public function testDeleteSourceDeprecatedAliasStillWorks(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        /** @phpstan-ignore method.deprecated (deprecated alias — verified still functional) */
        $client->data->deleteSource('src_1');
        $this->assertSame('DELETE', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/sources/src_1', $this->recorded[0]['url']);
    }
}
