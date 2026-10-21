<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Resources;

use Querri\Embed\Config;
use Querri\Embed\Exceptions\ErrorCode;
use Querri\Embed\Exceptions\NotFoundException;
use Querri\Embed\Exceptions\PermissionException;
use Querri\Embed\Exceptions\ValidationException;
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
            'ttl' => 900,
        ]);

        $body = $this->recorded[0]['body'] ?? '';
        $this->assertStringContainsString('"origin":"https://app.example.com"', $body);
        $this->assertStringContainsString('"ttl":900', $body);
    }

    public function testCreateSessionFallsBackToConfigDefaultOrigin(): void
    {
        $config = Config::resolve(
            apiKey: 'test_key',
            orgId: 'org_test',
            host: 'https://example.com',
            maxRetries: 0,
            defaultOrigin: 'https://default.example.com',
        );
        $client = $this->makeQuerriClient(
            [new MockResponse('{}', ['http_code' => 200])],
            $config,
        );

        $client->embed->createSession(['user_id' => 'u_1']);

        $this->assertStringContainsString(
            '"origin":"https://default.example.com"',
            $this->recorded[0]['body'] ?? '',
        );
    }

    public function testCreateSessionExplicitOriginBeatsDefaultOrigin(): void
    {
        $config = Config::resolve(
            apiKey: 'test_key',
            orgId: 'org_test',
            host: 'https://example.com',
            maxRetries: 0,
            defaultOrigin: 'https://default.example.com',
        );
        $client = $this->makeQuerriClient(
            [new MockResponse('{}', ['http_code' => 200])],
            $config,
        );

        $client->embed->createSession(['user_id' => 'u_1', 'origin' => 'https://explicit.example.com']);

        $body = $this->recorded[0]['body'] ?? '';
        $this->assertStringContainsString('"origin":"https://explicit.example.com"', $body);
        $this->assertStringNotContainsString('default.example.com', $body);
    }

    public function testCreateSessionRejectsTtlBelowMinimumWithoutHttpCall(): void
    {
        $client = $this->makeQuerriClient([]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('ttl must be between 900 and 86400');
        try {
            $client->embed->createSession(['user_id' => 'u_1', 'ttl' => 60]);
        } finally {
            $this->assertCount(0, $this->recorded); // guard fires before any HTTP
        }
    }

    public function testCreateSessionRejectsTtlAboveMaximum(): void
    {
        $client = $this->makeQuerriClient([]);

        $this->expectException(ValidationException::class);
        $client->embed->createSession(['user_id' => 'u_1', 'ttl' => 86401]);
    }

    public function testCreateSessionAcceptsTtlBounds(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{}', ['http_code' => 200]),
            new MockResponse('{}', ['http_code' => 200]),
        ]);

        $client->embed->createSession(['user_id' => 'u_1', 'ttl' => 900]);
        $client->embed->createSession(['user_id' => 'u_1', 'ttl' => 86400]);

        $this->assertCount(2, $this->recorded);
    }

    public function testCreateSessionRejectsOverlongOriginWithoutHttpCall(): void
    {
        $client = $this->makeQuerriClient([]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('origin must be at most 500 characters');
        try {
            $client->embed->createSession([
                'user_id' => 'u_1',
                'origin' => 'https://' . str_repeat('a', 500) . '.com',
            ]);
        } finally {
            $this->assertCount(0, $this->recorded);
        }
    }

    public function testCreateSessionSendsRoleAndReturnsRoleId(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse(
                '{"session_token":"es_t","expires_in":3600,"user_id":"u_1","role_id":"role_analyst"}',
                ['http_code' => 200],
            ),
        ]);

        $session = $client->embed->createSession(['user_id' => 'u_1', 'role' => 'analyst']);

        $this->assertSame('{"user_id":"u_1","ttl":3600,"role":"analyst"}', $this->recorded[0]['body']);
        $this->assertSame('role_analyst', $session['role_id'] ?? null);
    }

    public function testCreateSessionOmitsRoleKeyWhenNull(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->embed->createSession(['user_id' => 'u_1', 'role' => null]);

        $this->assertSame('{"user_id":"u_1","ttl":3600}', $this->recorded[0]['body']);
    }

    public function testCreateSessionOmitsRoleKeyWhenEmptyString(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->embed->createSession(['user_id' => 'u_1', 'role' => '']);

        $this->assertSame('{"user_id":"u_1","ttl":3600}', $this->recorded[0]['body']);
    }

    public function testCreateSessionRejectsOverlongRoleWithoutHttpCall(): void
    {
        $client = $this->makeQuerriClient([]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('role must be at most 100 characters, got 101.');
        try {
            $client->embed->createSession(['user_id' => 'u_1', 'role' => str_repeat('r', 101)]);
        } finally {
            $this->assertCount(0, $this->recorded);
        }
    }

    public function testCreateSessionAcceptsRoleAtMaxLength(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->embed->createSession(['user_id' => 'u_1', 'role' => str_repeat('r', 100)]);

        $this->assertCount(1, $this->recorded);
    }

    public function testCreateSessionRoleDisabledSurfacesAsPermissionException(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse(
                '{"detail":{"error":{"type":"permission_error","code":"role_disabled",'
                . '"message":"That role is a draft; an admin must enable it first.",'
                . '"doc_url":"https://docs.querri.com/api/errors#role_disabled"}}}',
                ['http_code' => 403, 'response_headers' => ['Content-Type: application/json']],
            ),
        ]);

        try {
            $client->embed->createSession(['user_id' => 'u_1', 'role' => 'draft_role']);
            $this->fail('expected PermissionException');
        } catch (PermissionException $e) {
            $this->assertSame(ErrorCode::ROLE_DISABLED, $e->errorCode);
            $this->assertSame('That role is a draft; an admin must enable it first.', $e->getMessage());
            $this->assertSame('https://docs.querri.com/api/errors#role_disabled', $e->docUrl);
        }
    }

    public function testCreateSessionUnknownRoleSurfacesAsNotFoundException(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse(
                '{"detail":{"error":{"type":"not_found_error","code":"role_not_found",'
                . '"message":"No role \'nope\' in this organization.",'
                . '"doc_url":"https://docs.querri.com/api/errors#role_not_found"}}}',
                ['http_code' => 404],
            ),
        ]);

        try {
            $client->embed->createSession(['user_id' => 'u_1', 'role' => 'nope']);
            $this->fail('expected NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertSame(ErrorCode::ROLE_NOT_FOUND, $e->errorCode);
        }
    }

    public function testRefreshSessionPassesRoleIdThrough(): void
    {
        // Response shape of the backend once refresh echoes the session's role.
        $client = $this->makeQuerriClient([
            new MockResponse(
                '{"session_token":"es_new","expires_in":3600,"user_id":"u_1","role_id":"role_analyst"}',
                ['http_code' => 200],
            ),
        ]);

        $refreshed = $client->embed->refreshSession('es_old');

        $this->assertSame('es_new', $refreshed['session_token']);
        $this->assertSame('role_analyst', $refreshed['role_id'] ?? null);
    }

    public function testListSessionsPassesRoleIdThrough(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse(
                '{"data":[{"session_token":"es_a","user_id":"u_1","role_id":"role_analyst"},'
                . '{"session_token":"es_b","user_id":"u_2"}],"has_more":false,"next_cursor":null}',
                ['http_code' => 200],
            ),
        ]);

        $list = $client->embed->listSessions();

        $this->assertSame('role_analyst', $list['data'][0]['role_id'] ?? null);
        $this->assertArrayNotHasKey('role_id', $list['data'][1]);
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
