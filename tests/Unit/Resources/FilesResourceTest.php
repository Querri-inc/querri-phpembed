<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Resources;

use Querri\Embed\Tests\Unit\MockHttpTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FilesResourceTest extends MockHttpTestCase
{
    public function testListGets(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);
        $client->files->list(['limit' => 5]);
        $this->assertStringContainsString('/files?limit=5', $this->recorded[0]['url']);
    }

    public function testRetrieveEncodesId(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->files->retrieve('f/1');
        $this->assertStringContainsString('/files/f%2F1', $this->recorded[0]['url']);
    }

    public function testDelDeletes(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->files->del('f_1');
        $this->assertSame('DELETE', $this->recorded[0]['method']);
    }
}
