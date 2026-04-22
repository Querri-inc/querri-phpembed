<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Resources;

use Querri\Embed\Tests\Unit\MockHttpTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class EmbedResourceTest extends MockHttpTestCase
{
    public function testCreateSessionPostsWithDefaultTtl(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"session_token":"t","expires_in":3600,"user_id":"u"}', ['http_code' => 200]),
        ]);

        $client->embed->createSession(['user_id' => 'u_1']);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/embed/sessions', $this->recorded[0]['url']);
        $this->assertSame('{"user_id":"u_1","ttl":3600}', $this->recorded[0]['body']);
    }

    public function testCreateSessionIncludesOriginWhenProvided(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->embed->createSession([
            'user_id' => 'u_1',
            'origin' => 'https://app.example.com',
            'ttl' => 60,
        ]);

        $body = $this->recorded[0]['body'] ?? '';
        $this->assertStringContainsString('"origin":"https://app.example.com"', $body);
        $this->assertStringContainsString('"ttl":60', $body);
    }

    public function testRefreshSessionPostsSessionToken(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->embed->refreshSession('tok_abc');

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/embed/sessions/refresh', $this->recorded[0]['url']);
        $this->assertSame('{"session_token":"tok_abc"}', $this->recorded[0]['body']);
    }

    public function testListSessionsDefaultsLimit100(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);

        $client->embed->listSessions();

        $this->assertStringContainsString('limit=100', $this->recorded[0]['url']);
    }

    public function testListSessionsCallerLimitOverridesDefault(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);

        $client->embed->listSessions(['limit' => 25]);

        $this->assertStringContainsString('limit=25', $this->recorded[0]['url']);
        $this->assertStringNotContainsString('limit=100', $this->recorded[0]['url']);
    }

    public function testRevokeSessionDeletesEncodedId(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->embed->revokeSession('tok/with/slash');

        $this->assertSame('DELETE', $this->recorded[0]['method']);
        $this->assertStringContainsString('/embed/sessions/tok%2Fwith%2Fslash', $this->recorded[0]['url']);
    }

    public function testRevokeUserSessionsIteratesAndFilters(): void
    {
        $client = $this->makeQuerriClient([
            // listSessions
            new MockResponse(
                json_encode([
                    'data' => [
                        ['session_token' => 'tok_a', 'user_id' => 'u_1'],
                        ['session_token' => 'tok_b', 'user_id' => 'u_2'],
                        ['session_token' => 'tok_c', 'user_id' => 'u_1'],
                    ],
                    'has_more' => false,
                    'next_cursor' => null,
                ]) ?: '{}',
                ['http_code' => 200],
            ),
            // revokeSession tok_a
            new MockResponse('{}', ['http_code' => 200]),
            // revokeSession tok_c
            new MockResponse('{}', ['http_code' => 200]),
        ]);

        $count = $client->embed->revokeUserSessions('u_1');

        $this->assertSame(2, $count);
        $this->assertCount(3, $this->recorded); // list + 2 revokes
        $this->assertStringContainsString('/embed/sessions/tok_a', $this->recorded[1]['url']);
        $this->assertStringContainsString('/embed/sessions/tok_c', $this->recorded[2]['url']);
    }
}
