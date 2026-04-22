<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Resources;

use Querri\Embed\Tests\Unit\MockHttpTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class UsersResourceTest extends MockHttpTestCase
{
    public function testListSendsGetWithQueryParams(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);

        $client->users->list(['limit' => 10, 'external_id' => 'ext_x']);

        $this->assertSame('GET', $this->recorded[0]['method']);
        $this->assertStringContainsString('/users?', $this->recorded[0]['url']);
        $this->assertStringContainsString('limit=10', $this->recorded[0]['url']);
        $this->assertStringContainsString('external_id=ext_x', $this->recorded[0]['url']);
    }

    public function testRetrieveUrlEncodesId(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->users->retrieve('user/with/slashes');

        $this->assertStringContainsString('user%2Fwith%2Fslashes', $this->recorded[0]['url']);
    }

    public function testCreatePostsJsonBody(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->users->create(['email' => 'a@b.com', 'first_name' => 'A']);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/users', $this->recorded[0]['url']);
        $this->assertSame('{"email":"a@b.com","first_name":"A"}', $this->recorded[0]['body']);
    }

    public function testUpdateSendsPatch(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->users->update('u_1', ['role' => 'admin']);

        $this->assertSame('PATCH', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/users/u_1', $this->recorded[0]['url']);
        $this->assertSame('{"role":"admin"}', $this->recorded[0]['body']);
    }

    public function testGetOrCreatePutsToExternalIdPath(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->users->getOrCreate('ext_99', ['email' => 'a@b.com']);

        $this->assertSame('PUT', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/users/external/ext_99', $this->recorded[0]['url']);
        $this->assertSame('{"email":"a@b.com"}', $this->recorded[0]['body']);
    }

    public function testGetOrCreateSendsEmptyObjectWhenParamsOmitted(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->users->getOrCreate('ext_99');

        $this->assertSame('{}', $this->recorded[0]['body']);
    }

    public function testDelSendsDelete(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->users->del('u_1');

        $this->assertSame('DELETE', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/users/u_1', $this->recorded[0]['url']);
    }

    public function testRemoveExternalIdTargetsExternalPath(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->users->removeExternalId('ext_99');

        $this->assertSame('DELETE', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/users/external/ext_99', $this->recorded[0]['url']);
    }
}
