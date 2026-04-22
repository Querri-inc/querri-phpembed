<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Querri\Embed\Config;
use Querri\Embed\Exceptions\ApiException;
use Querri\Embed\Exceptions\AuthenticationException;
use Querri\Embed\Exceptions\ConflictException;
use Querri\Embed\Exceptions\ConnectionException;
use Querri\Embed\Exceptions\NotFoundException;
use Querri\Embed\Exceptions\PermissionException;
use Querri\Embed\Exceptions\RateLimitException;
use Querri\Embed\Exceptions\ServerException;
use Querri\Embed\Exceptions\TimeoutException;
use Querri\Embed\Exceptions\ValidationException;
use Querri\Embed\Http\HttpClient;
use Symfony\Component\HttpClient\Exception\TimeoutException as SymfonyTimeoutException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpClientTest extends TestCase
{
    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $recorded;

    /**
     * Build an HttpClient wired around a MockHttpClient whose responses are
     * driven by $responses (in order) and whose outbound calls are recorded
     * into $this->recorded for assertions.
     *
     * @param list<MockResponse|\Throwable> $responses
     */
    private function buildClient(
        array $responses,
        ?Config $config = null,
    ): HttpClient {
        $this->recorded = [];
        $recorded = &$this->recorded;

        $iterator = new \ArrayIterator($responses);

        $mock = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$recorded, $iterator) {
                $recorded[] = ['method' => $method, 'url' => $url, 'options' => $options];
                if (!$iterator->valid()) {
                    throw new \LogicException('Mock ran out of responses for ' . $method . ' ' . $url);
                }
                $next = $iterator->current();
                $iterator->next();
                if ($next instanceof \Throwable) {
                    throw $next;
                }
                return $next;
            },
        );

        $config ??= Config::resolve(
            apiKey: 'test_key',
            orgId: 'org_test',
            host: 'https://example.com',
            maxRetries: 1,
        );

        return new HttpClient($config, $mock);
    }

    // ─── Success paths ──────────────────────────────────────────────

    public function testReturnsDecodedJsonOn200(): void
    {
        $client = $this->buildClient([
            new MockResponse('{"id":"u_1","name":"Alice"}', ['http_code' => 200]),
        ]);

        $result = $client->request(['method' => 'GET', 'path' => '/users/u_1']);

        $this->assertSame(['id' => 'u_1', 'name' => 'Alice'], $result);
    }

    public function testReturnsEmptyArrayOn204(): void
    {
        $client = $this->buildClient([
            new MockResponse('', ['http_code' => 204]),
        ]);

        $result = $client->request(['method' => 'DELETE', 'path' => '/users/u_1']);

        $this->assertSame([], $result);
    }

    public function testThrowsConnectionExceptionOnNonJsonSuccessResponse(): void
    {
        $client = $this->buildClient([
            new MockResponse('<html>gateway page</html>', ['http_code' => 200]),
        ]);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Invalid JSON response from API');
        $client->request(['method' => 'GET', 'path' => '/users']);
    }

    // ─── Error mapping ──────────────────────────────────────────────

    /** @return array<string, array{int, class-string<ApiException>}> */
    public static function errorStatusCases(): array
    {
        return [
            '400 → Validation' => [400, ValidationException::class],
            '401 → Authentication' => [401, AuthenticationException::class],
            '403 → Permission' => [403, PermissionException::class],
            '404 → NotFound' => [404, NotFoundException::class],
            '409 → Conflict' => [409, ConflictException::class],
            '418 → generic ApiException' => [418, ApiException::class],
        ];
    }

    /** @param class-string<ApiException> $expected */
    #[DataProvider('errorStatusCases')]
    public function testHttpErrorsMapToExceptionSubclasses(int $status, string $expected): void
    {
        $client = $this->buildClient([
            new MockResponse('{"error":{"message":"nope"}}', ['http_code' => $status]),
        ]);

        $this->expectException($expected);
        $client->request(['method' => 'POST', 'path' => '/x']);
    }

    public function testServerExceptionOn500AfterRetriesExhausted(): void
    {
        $client = $this->buildClient([
            new MockResponse('{"error":"first"}', ['http_code' => 500]),
            new MockResponse('{"error":"second"}', ['http_code' => 500]),
        ]);

        try {
            $client->request(['method' => 'GET', 'path' => '/x']);
            $this->fail('expected ServerException');
        } catch (ServerException $e) {
            $this->assertSame(500, $e->status);
            $this->assertCount(2, $this->recorded);
        }
    }

    // ─── Retry behavior ─────────────────────────────────────────────

    public function testIdempotentGetRetriesAndSucceeds(): void
    {
        $client = $this->buildClient([
            new MockResponse('{"error":"transient"}', ['http_code' => 500]),
            new MockResponse('{"ok":true}', ['http_code' => 200]),
        ]);

        $result = $client->request(['method' => 'GET', 'path' => '/x']);

        $this->assertSame(['ok' => true], $result);
        $this->assertCount(2, $this->recorded);
    }

    public function testNonIdempotentPostDoesNotRetryOn500(): void
    {
        $client = $this->buildClient([
            new MockResponse('{"error":"busted"}', ['http_code' => 500]),
        ]);

        try {
            $client->request(['method' => 'POST', 'path' => '/x', 'body' => ['a' => 1]]);
            $this->fail('expected ServerException');
        } catch (ServerException) {
            $this->assertCount(1, $this->recorded); // no retry
        }
    }

    public function test429AlwaysRetriesEvenForPost(): void
    {
        $client = $this->buildClient([
            new MockResponse('{"error":"slow down"}', ['http_code' => 429, 'response_headers' => ['retry-after' => '0']]),
            new MockResponse('{"ok":true}', ['http_code' => 200]),
        ]);

        $result = $client->request(['method' => 'POST', 'path' => '/x', 'body' => ['a' => 1]]);

        $this->assertSame(['ok' => true], $result);
        $this->assertCount(2, $this->recorded);
    }

    public function testIdempotentRequestRetriesOnTimeout(): void
    {
        $client = $this->buildClient([
            new SymfonyTimeoutException('simulated timeout'),
            new MockResponse('{"ok":true}', ['http_code' => 200]),
        ]);

        $result = $client->request(['method' => 'GET', 'path' => '/x']);

        $this->assertSame(['ok' => true], $result);
        $this->assertCount(2, $this->recorded);
    }

    public function testNonIdempotentRequestDoesNotRetryOnTimeout(): void
    {
        $client = $this->buildClient([
            new SymfonyTimeoutException('simulated timeout'),
        ]);

        $this->expectException(TimeoutException::class);
        $this->expectExceptionMessage('Request timed out');
        $client->request(['method' => 'POST', 'path' => '/x', 'body' => ['a' => 1]]);
    }

    public function testIdempotentRequestRetriesOnTransportError(): void
    {
        $client = $this->buildClient([
            new TransportException('connection reset'),
            new MockResponse('{"ok":true}', ['http_code' => 200]),
        ]);

        $result = $client->request(['method' => 'GET', 'path' => '/x']);

        $this->assertSame(['ok' => true], $result);
    }

    public function testNonIdempotentRequestDoesNotRetryOnTransportError(): void
    {
        $client = $this->buildClient([
            new TransportException('connection reset'),
        ]);

        $this->expectException(ConnectionException::class);
        $client->request(['method' => 'POST', 'path' => '/x']);
    }

    // ─── Auth headers ───────────────────────────────────────────────

    public function testBearerAuthAndTenantHeadersForApiKeyConfig(): void
    {
        $client = $this->buildClient([
            new MockResponse('{}', ['http_code' => 200]),
        ]);

        $client->request(['method' => 'GET', 'path' => '/users']);

        $headers = $this->recordedNormalizedHeaders(0);
        $this->assertSame('Bearer test_key', $headers['authorization']);
        $this->assertSame('org_test', $headers['x-tenant-id']);
        $this->assertArrayNotHasKey('x-embed-session', $headers);
    }

    public function testSessionHeaderInsteadOfBearerForSessionConfig(): void
    {
        $config = Config::forSession(sessionToken: 'sess_abc', host: 'https://example.com');
        $client = $this->buildClient(
            [new MockResponse('{}', ['http_code' => 200])],
            config: $config,
        );

        $client->request(['method' => 'GET', 'path' => '/embed/dashboards/d_1']);

        $headers = $this->recordedNormalizedHeaders(0);
        $this->assertSame('sess_abc', $headers['x-embed-session']);
        $this->assertArrayNotHasKey('authorization', $headers);
        $this->assertArrayNotHasKey('x-tenant-id', $headers);

        // forSession uses /api (not /api/v1)
        $this->assertSame('https://example.com/api/embed/dashboards/d_1', $this->recorded[0]['url']);
    }

    public function testTenantHeaderOmittedWhenOrgIdNull(): void
    {
        $config = Config::resolve(apiKey: 'k', host: 'https://example.com');
        // orgId is null
        $client = $this->buildClient(
            [new MockResponse('{}', ['http_code' => 200])],
            config: $config,
        );

        $client->request(['method' => 'GET', 'path' => '/users']);

        $headers = $this->recordedNormalizedHeaders(0);
        $this->assertArrayNotHasKey('x-tenant-id', $headers);
    }

    public function testUserAgentHeaderIncludesVersion(): void
    {
        $client = $this->buildClient([
            new MockResponse('{}', ['http_code' => 200]),
        ]);

        $client->request(['method' => 'GET', 'path' => '/users']);

        $headers = $this->recordedNormalizedHeaders(0);
        $this->assertStringStartsWith('querri-php/', $headers['user-agent']);
    }

    // ─── URL + query + body ─────────────────────────────────────────

    public function testQueryParamsAppendedToUrl(): void
    {
        $client = $this->buildClient([
            new MockResponse('{}', ['http_code' => 200]),
        ]);

        $client->request([
            'method' => 'GET',
            'path' => '/users',
            'query' => ['limit' => 10, 'after' => 'cursor_x'],
        ]);

        $this->assertStringContainsString('limit=10', $this->recorded[0]['url']);
        $this->assertStringContainsString('after=cursor_x', $this->recorded[0]['url']);
    }

    public function testNullQueryValuesFiltered(): void
    {
        $client = $this->buildClient([
            new MockResponse('{}', ['http_code' => 200]),
        ]);

        $client->request([
            'method' => 'GET',
            'path' => '/users',
            'query' => ['limit' => 10, 'after' => null],
        ]);

        $this->assertStringContainsString('limit=10', $this->recorded[0]['url']);
        $this->assertStringNotContainsString('after', $this->recorded[0]['url']);
    }

    public function testPostBodyIsJsonEncoded(): void
    {
        $client = $this->buildClient([
            new MockResponse('{}', ['http_code' => 200]),
        ]);

        $client->request([
            'method' => 'POST',
            'path' => '/users',
            'body' => ['email' => 'a@b.com'],
        ]);

        $this->assertSame('{"email":"a@b.com"}', $this->recorded[0]['options']['body']);
        $headers = $this->recordedNormalizedHeaders(0);
        $this->assertSame('application/json', $headers['content-type']);
    }

    public function testEmptyArrayBodyEncodesAsJsonObject(): void
    {
        // JSON_FORCE_OBJECT ensures `[]` serializes as `{}` so the API parses
        // it as an object, not a list. Critical for PATCH endpoints that treat
        // `[]` as "no fields" vs `{}` as "no replacement".
        $client = $this->buildClient([
            new MockResponse('{}', ['http_code' => 200]),
        ]);

        $client->request([
            'method' => 'POST',
            'path' => '/x',
            'body' => [],
        ]);

        $this->assertSame('{}', $this->recorded[0]['options']['body']);
    }

    public function testNoBodyMeansNoContentTypeHeader(): void
    {
        $client = $this->buildClient([
            new MockResponse('{}', ['http_code' => 200]),
        ]);

        $client->request(['method' => 'GET', 'path' => '/users']);

        $headers = $this->recordedNormalizedHeaders(0);
        $this->assertArrayNotHasKey('content-type', $headers);
    }

    public function testPerRequestHeadersMergeWithDefaults(): void
    {
        $client = $this->buildClient([
            new MockResponse('{}', ['http_code' => 200]),
        ]);

        $client->request([
            'method' => 'GET',
            'path' => '/users',
            'headers' => ['X-Idempotency-Key' => 'idem_abc'],
        ]);

        $headers = $this->recordedNormalizedHeaders(0);
        $this->assertSame('idem_abc', $headers['x-idempotency-key']);
        $this->assertSame('Bearer test_key', $headers['authorization']); // defaults preserved
    }

    // ─── Helpers ────────────────────────────────────────────────────

    /**
     * Symfony's MockHttpClient callable receives options with the headers
     * pre-flattened into $options['headers'] as list<string> in "Name: Value"
     * form. This parses that list into a lowercase-keyed associative array
     * for easy assertions.
     *
     * @return array<string, string>
     */
    private function recordedNormalizedHeaders(int $index): array
    {
        $raw = $this->recorded[$index]['options']['headers'] ?? [];
        $out = [];
        foreach ((array) $raw as $line) {
            if (!is_string($line) || !str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $out[strtolower(trim($name))] = trim($value);
        }
        return $out;
    }
}
