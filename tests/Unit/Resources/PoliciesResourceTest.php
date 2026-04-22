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

    public function testRemoveUserDeletes(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->removeUser('pol_1', 'u_1');

        $this->assertSame('DELETE', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/access/policies/pol_1/users/u_1', $this->recorded[0]['url']);
    }

    // ─── assignUsers — new + legacy (deprecated) shapes ─────────────

    public function testAssignUsersWithPreferredShape(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->assignUsers('pol_1', ['user_ids' => ['u_1', 'u_2']]);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/access/policies/pol_1/users', $this->recorded[0]['url']);
        $this->assertSame('{"user_ids":["u_1","u_2"]}', $this->recorded[0]['body']);
    }

    public function testAssignUsersLegacyBareListStillWorks(): void
    {
        // Deprecated path — retained until 0.3.0
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->assignUsers('pol_1', ['u_1', 'u_2']);

        $this->assertSame('{"user_ids":["u_1","u_2"]}', $this->recorded[0]['body']);
    }

    // ─── replaceUserPolicies — new + legacy shapes ──────────────────

    public function testReplaceUserPoliciesWithPreferredShape(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->replaceUserPolicies('u_1', ['policy_ids' => ['pol_a', 'pol_b']]);

        $this->assertSame('PUT', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/access/users/u_1/policies', $this->recorded[0]['url']);
        $this->assertSame('{"policy_ids":["pol_a","pol_b"]}', $this->recorded[0]['body']);
    }

    public function testReplaceUserPoliciesLegacyBareListStillWorks(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->replaceUserPolicies('u_1', ['pol_a', 'pol_b']);

        $this->assertSame('{"policy_ids":["pol_a","pol_b"]}', $this->recorded[0]['body']);
    }

    public function testReplaceUserPoliciesEmptyListClearsAssignments(): void
    {
        // Empty PHP list (array_is_list([]) === true) — still legacy-detected,
        // so {policy_ids: []} lands correctly.
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->replaceUserPolicies('u_1', []);

        $this->assertSame('{"policy_ids":[]}', $this->recorded[0]['body']);
    }

    // ─── resolveAccess (primary) + resolve (alias) ──────────────────

    public function testResolveAccessPostsUserAndSource(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->policies->resolveAccess('u_1', 'src_1');

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/access/resolve', $this->recorded[0]['url']);
        $this->assertSame('{"user_id":"u_1","source_id":"src_1"}', $this->recorded[0]['body']);
    }

    public function testResolveDeprecatedAliasStillWorks(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);
        /** @phpstan-ignore method.deprecated (deprecated alias — verified still functional) */
        $client->policies->resolve('u_1', 'src_1');
        $this->assertStringEndsWith('/access/resolve', $this->recorded[0]['url']);
    }

    // ─── listColumns (primary) + columns (alias) ────────────────────

    public function testListColumnsUnwrapsDataArray(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse(
                '{"data":[{"source_id":"s_1","source_name":"Users","columns":[]}]}',
                ['http_code' => 200],
            ),
        ]);

        $result = $client->policies->listColumns('s_1');

        $this->assertSame([
            ['source_id' => 's_1', 'source_name' => 'Users', 'columns' => []],
        ], $result);
    }

    public function testColumnsDeprecatedAliasStillUnwraps(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse(
                '{"data":[{"source_id":"s_1","source_name":"Users","columns":[]}]}',
                ['http_code' => 200],
            ),
        ]);

        /** @phpstan-ignore method.deprecated (deprecated alias — verified still functional) */
        $result = $client->policies->columns('s_1');

        $this->assertSame([
            ['source_id' => 's_1', 'source_name' => 'Users', 'columns' => []],
        ], $result);
    }
}
