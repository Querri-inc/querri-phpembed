<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Resources;

use Querri\Embed\Tests\Unit\MockHttpTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ChatsResourceTest extends MockHttpTestCase
{
    public function testCreatePostsToNestedPath(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->chats->create('p_1', ['name' => 'Chat']);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/projects/p_1/chats', $this->recorded[0]['url']);
        $this->assertSame('{"name":"Chat"}', $this->recorded[0]['body']);
    }

    public function testCreateSendsNullBodyWhenParamsEmpty(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->chats->create('p_1');

        // Empty params → null body → no Content-Type, no body on wire
        $this->assertNull($this->recorded[0]['body']);
    }

    public function testListGetsNestedPath(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);

        $client->chats->list('p_1', ['limit' => 5]);

        $this->assertStringContainsString('/projects/p_1/chats?limit=5', $this->recorded[0]['url']);
    }

    public function testRetrieveEncodesBothIds(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->chats->retrieve('p/1', 'c/1');

        $this->assertStringContainsString('/projects/p%2F1/chats/c%2F1', $this->recorded[0]['url']);
    }

    public function testDelDeletes(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->chats->del('p_1', 'c_1');

        $this->assertSame('DELETE', $this->recorded[0]['method']);
    }

    public function testCancelPosts(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->chats->cancel('p_1', 'c_1');

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/projects/p_1/chats/c_1/cancel', $this->recorded[0]['url']);
    }
}
