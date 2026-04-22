<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Resources;

use Querri\Embed\Tests\Unit\MockHttpTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PoliciesResourceTest extends MockHttpTestCase
{
    public function testCreatePostsPolicy(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->create([
            'name' => 'p_a',
            'source_ids' => ['s_1'],
            'row_filters' => [['column' => 'region', 'values' => ['us']]],
        ]);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/access/policies', $this->recorded[0]['url']);
        $this->assertStringContainsString('"name":"p_a"', $this->recorded[0]['body'] ?? '');
    }

    public function testListAcceptsNameFilter(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);

        $client->policies->list(['name' => 'sdk_auto_abc']);

        $this->assertStringContainsString('name=sdk_auto_abc', $this->recorded[0]['url']);
    }

    public function testRetrieveEncodesId(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->retrieve('pol/with/slash');

        $this->assertStringContainsString('pol%2Fwith%2Fslash', $this->recorded[0]['url']);
    }

    public function testUpdatePatches(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->update('pol_1', ['name' => 'renamed']);

        $this->assertSame('PATCH', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/access/policies/pol_1', $this->recorded[0]['url']);
    }

    public function testDelDeletes(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->del('pol_1');

        $this->assertSame('DELETE', $this->recorded[0]['method']);
    }

    public function testAssignUsersPostsWithBody(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->assignUsers('pol_1', ['u_1', 'u_2']);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/access/policies/pol_1/users', $this->recorded[0]['url']);
        $this->assertSame('{"user_ids":["u_1","u_2"]}', $this->recorded[0]['body']);
    }

    public function testRemoveUserDeletes(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->removeUser('pol_1', 'u_1');

        $this->assertSame('DELETE', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/access/policies/pol_1/users/u_1', $this->recorded[0]['url']);
    }

    public function testReplaceUserPoliciesPutsAtomicPolicyList(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->replaceUserPolicies('u_1', ['pol_a', 'pol_b']);

        $this->assertSame('PUT', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/access/users/u_1/policies', $this->recorded[0]['url']);
        $this->assertSame('{"policy_ids":["pol_a","pol_b"]}', $this->recorded[0]['body']);
    }

    public function testReplaceUserPoliciesAcceptsEmptyList(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->replaceUserPolicies('u_1', []);

        $this->assertSame('{"policy_ids":[]}', $this->recorded[0]['body']);
    }

    public function testResolvePostsUserAndSource(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->resolve('u_1', 'src_1');

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/access/resolve', $this->recorded[0]['url']);
        $this->assertSame('{"user_id":"u_1","source_id":"src_1"}', $this->recorded[0]['body']);
    }

    public function testColumnsUnwrapsDataArray(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse(
                '{"data":[{"source_id":"s_1","source_name":"Users","columns":[]}]}',
                ['http_code' => 200],
            ),
        ]);

        $result = $client->policies->columns('s_1');

        $this->assertSame([
            ['source_id' => 's_1', 'source_name' => 'Users', 'columns' => []],
        ], $result);
    }
}
