<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Resources;

use Querri\Embed\Tests\Unit\MockHttpTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class KeysResourceTest extends MockHttpTestCase
{
    public function testCreatePosts(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->keys->create(['name' => 'ci', 'scopes' => ['read']]);
        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/keys', $this->recorded[0]['url']);
    }

    public function testListGets(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);
        $client->keys->list();
        $this->assertSame('GET', $this->recorded[0]['method']);
    }

    public function testRetrieveGets(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->keys->retrieve('k_1');
        $this->assertStringEndsWith('/keys/k_1', $this->recorded[0]['url']);
    }

    public function testRevokeDeletes(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        $client->keys->revoke('k_1');
        $this->assertSame('DELETE', $this->recorded[0]['method']);
    }
}
