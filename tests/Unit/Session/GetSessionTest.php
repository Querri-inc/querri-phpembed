<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Session;

use PHPUnit\Framework\TestCase;
use Querri\Embed\Config;
use Querri\Embed\Exceptions\ConfigException;
use Querri\Embed\QuerriClient;
use Querri\Embed\Session\GetSession;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GetSessionTest extends TestCase
{
    /** @var list<array{method: string, url: string, body: ?string}> */
    private array $recorded;

    /**
     * Build a real QuerriClient wired around a MockHttpClient whose responses
     * are delivered in order.
     *
     * @param list<MockResponse> $responses
     */
    private function buildClient(array $responses): QuerriClient
    {
        $this->recorded = [];
        $recorded = &$this->recorded;
        $iterator = new \ArrayIterator($responses);

        $mock = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$recorded, $iterator) {
                $body = null;
                if (isset($options['body']) && is_string($options['body'])) {
                    $body = $options['body'];
                }
                $recorded[] = ['method' => $method, 'url' => $url, 'body' => $body];
                if (!$iterator->valid()) {
                    throw new \LogicException('Mock ran out of responses for ' . $method . ' ' . $url);
                }
                $next = $iterator->current();
                $iterator->next();
                return $next;
            },
        );

        $config = Config::resolve(
            apiKey: 'test_key',
            orgId: 'org_test',
            host: 'https://example.com',
            maxRetries: 0, // no retries in these tests
        );

        return new QuerriClient($config, $mock);
    }

    /**
     * Helper: compute the same deterministic 8-hex-char hash the code uses
     * for policy naming, so tests can produce the exact sdk_auto_{hash} name
     * the production code will look up.
     *
     * @param array<string, mixed> $access
     */
    private function computeHash(array $access): string
    {
        $method = new \ReflectionMethod(GetSession::class, 'hashAccessSpec');
        $method->setAccessible(true);
        $result = $method->invoke(null, $access);
        $this->assertIsString($result);
        return $result;
    }

    // ─── Defensive input validation ────────────────────────────────

    public function testThrowsWhenUserIsMissing(): void
    {
        $client = $this->buildClient([]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("'user' parameter is required");

        /** @phpstan-ignore argument.type (intentionally malformed to exercise runtime check) */
        GetSession::execute($client, []);
    }

    public function testThrowsWhenUserArrayMissingExternalId(): void
    {
        $client = $this->buildClient([]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("'external_id' field is required");

        /** @phpstan-ignore argument.type (intentionally malformed to exercise runtime check) */
        GetSession::execute($client, ['user' => ['email' => 'a@b.com']]);
    }

    // ─── hashAccessSpec via reflection ─────────────────────────────

    public function testHashAccessSpecIsDeterministic(): void
    {
        $method = new \ReflectionMethod(GetSession::class, 'hashAccessSpec');
        $method->setAccessible(true);

        $a = $method->invoke(null, ['sources' => ['s1', 's2'], 'filters' => ['region' => 'us']]);
        $b = $method->invoke(null, ['sources' => ['s1', 's2'], 'filters' => ['region' => 'us']]);

        $this->assertSame($a, $b);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $a);
    }

    public function testHashAccessSpecNormalizesSourceAndFilterOrder(): void
    {
        $method = new \ReflectionMethod(GetSession::class, 'hashAccessSpec');
        $method->setAccessible(true);

        $unsorted = $method->invoke(null, [
            'sources' => ['b', 'a'],
            'filters' => ['z' => ['2', '1'], 'a' => 'x'],
        ]);
        $sorted = $method->invoke(null, [
            'sources' => ['a', 'b'],
            'filters' => ['a' => 'x', 'z' => ['1', '2']],
        ]);

        $this->assertSame($sorted, $unsorted);
    }

    public function testHashAccessSpecEmptyFiltersEncodesAsJsonObject(): void
    {
        // Known footgun: without the `(object)` cast, an empty PHP array encodes
        // as `[]` in JSON, not `{}`. This would break hash parity with the JS
        // SDK which sends `{}`. This test locks that down with a direct string
        // comparison against the expected JSON.
        $method = new \ReflectionMethod(GetSession::class, 'hashAccessSpec');
        $method->setAccessible(true);

        $actual = $method->invoke(null, ['sources' => ['s1'], 'filters' => []]);

        $expectedJson = '{"sources":["s1"],"filters":{}}';
        $expectedHash = substr(hash('sha256', $expectedJson), 0, 8);

        $this->assertSame($expectedHash, $actual);
    }

    public function testHashAccessSpecWithNonEmptyFilters(): void
    {
        $method = new \ReflectionMethod(GetSession::class, 'hashAccessSpec');
        $method->setAccessible(true);

        $actual = $method->invoke(null, [
            'sources' => ['s1'],
            'filters' => ['region' => 'us'],
        ]);

        // After normalization: sources sorted, filters sorted by key, values
        // wrapped in arrays and sorted.
        $expectedJson = '{"sources":["s1"],"filters":{"region":["us"]}}';
        $expectedHash = substr(hash('sha256', $expectedJson), 0, 8);

        $this->assertSame($expectedHash, $actual);
    }

    // ─── buildRowFilters via reflection ────────────────────────────

    public function testBuildRowFiltersWrapsScalarValuesAsOneElementArrays(): void
    {
        $method = new \ReflectionMethod(GetSession::class, 'buildRowFilters');
        $method->setAccessible(true);

        $actual = $method->invoke(null, ['region' => 'us', 'tier' => ['free', 'pro']]);

        $this->assertSame([
            ['column' => 'region', 'values' => ['us']],
            ['column' => 'tier', 'values' => ['free', 'pro']],
        ], $actual);
    }

    public function testBuildRowFiltersEmptyInput(): void
    {
        $method = new \ReflectionMethod(GetSession::class, 'buildRowFilters');
        $method->setAccessible(true);

        $this->assertSame([], $method->invoke(null, []));
    }

    // ─── execute() end-to-end flow ─────────────────────────────────

    public function testExecuteWithStringUserAndNoAccess(): void
    {
        $client = $this->buildClient([
            // Step 1: users.getOrCreate
            new MockResponse('{"id":"usr_42","external_id":"ext_99"}', ['http_code' => 200]),
            // Step 3: embed.createSession (step 2 skipped — no access)
            new MockResponse(
                '{"session_token":"tok_abc","expires_in":3600,"user_id":"usr_42"}',
                ['http_code' => 200],
            ),
        ]);

        $result = GetSession::execute($client, ['user' => 'ext_99']);

        $this->assertSame('tok_abc', $result->sessionToken);
        $this->assertSame(3600, $result->expiresIn);
        $this->assertSame('usr_42', $result->userId);
        $this->assertSame('ext_99', $result->externalId);

        // Exactly 2 calls: getOrCreate + createSession
        $this->assertCount(2, $this->recorded);
        $this->assertSame('PUT', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/users/external/ext_99', $this->recorded[0]['url']);
        $this->assertSame('POST', $this->recorded[1]['method']);
        $this->assertStringEndsWith('/embed/sessions', $this->recorded[1]['url']);
    }

    public function testExecuteWithArrayUserSendsOnlyProvidedFields(): void
    {
        $client = $this->buildClient([
            new MockResponse('{"id":"usr_42","external_id":"ext_99"}', ['http_code' => 200]),
            new MockResponse('{"session_token":"t","expires_in":10,"user_id":"usr_42"}', ['http_code' => 200]),
        ]);

        GetSession::execute($client, [
            'user' => [
                'external_id' => 'ext_99',
                'email' => 'a@b.com',
                // first_name, last_name, role omitted
            ],
        ]);

        $this->assertSame('{"email":"a@b.com"}', $this->recorded[0]['body']);
    }

    public function testExecuteWithPolicyIdsUsesThemDirectly(): void
    {
        $client = $this->buildClient([
            new MockResponse('{"id":"usr_42"}', ['http_code' => 200]),         // users.getOrCreate
            new MockResponse('{"assigned":["pol_1"]}', ['http_code' => 200]),  // replaceUserPolicies
            new MockResponse('{"session_token":"t","expires_in":10,"user_id":"usr_42"}', ['http_code' => 200]),
        ]);

        GetSession::execute($client, [
            'user' => 'ext_99',
            'access' => ['policy_ids' => ['pol_1', 'pol_2']],
        ]);

        $this->assertCount(3, $this->recorded);
        $this->assertSame('PUT', $this->recorded[1]['method']);
        $this->assertStringContainsString('/access/users/usr_42/policies', $this->recorded[1]['url']);
        $this->assertSame(
            '{"policy_ids":["pol_1","pol_2"]}',
            $this->recorded[1]['body'],
        );
    }

    public function testExecuteWithInlineAccessCreatesPolicyWhenMissing(): void
    {
        $client = $this->buildClient([
            new MockResponse('{"id":"usr_42"}', ['http_code' => 200]),
            // policies.list: not found
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
            // policies.create: succeeds
            new MockResponse('{"id":"pol_new","name":"sdk_auto_xxx"}', ['http_code' => 200]),
            // replaceUserPolicies
            new MockResponse('{"assigned":["pol_new"]}', ['http_code' => 200]),
            // embed.createSession
            new MockResponse('{"session_token":"t","expires_in":10,"user_id":"usr_42"}', ['http_code' => 200]),
        ]);

        GetSession::execute($client, [
            'user' => 'ext_99',
            'access' => [
                'sources' => ['src_a'],
                'filters' => ['region' => 'us'],
            ],
        ]);

        $this->assertCount(5, $this->recorded);
        $this->assertStringContainsString('/access/policies?name=sdk_auto_', $this->recorded[1]['url']);
        $this->assertSame('POST', $this->recorded[2]['method']);
        $this->assertStringEndsWith('/access/policies', $this->recorded[2]['url']);
    }

    public function testExecuteReusesExistingPolicyWhenFound(): void
    {
        $policyName = 'sdk_auto_' . $this->computeHash(['sources' => ['src_a'], 'filters' => []]);
        $listBody = json_encode([
            'data' => [['id' => 'pol_existing', 'name' => $policyName]],
            'has_more' => false,
            'next_cursor' => null,
        ]);
        $this->assertIsString($listBody);

        $client = $this->buildClient([
            new MockResponse('{"id":"usr_42"}', ['http_code' => 200]),
            // policies.list: found — name matches the computed sdk_auto_{hash}
            new MockResponse($listBody, ['http_code' => 200]),
            // replaceUserPolicies
            new MockResponse('{"assigned":["pol_existing"]}', ['http_code' => 200]),
            // embed.createSession
            new MockResponse('{"session_token":"t","expires_in":10,"user_id":"usr_42"}', ['http_code' => 200]),
        ]);

        GetSession::execute($client, [
            'user' => 'ext_99',
            'access' => ['sources' => ['src_a'], 'filters' => []],
        ]);

        // 4 calls (no create) — getOrCreate, list, replacePolicies, createSession
        $this->assertCount(4, $this->recorded);
        $this->assertStringContainsString('pol_existing', $this->recorded[2]['body'] ?? '');
    }

    public function testExecuteHandlesTocTouConflictByRefetching(): void
    {
        $policyName = 'sdk_auto_' . $this->computeHash(['sources' => ['src_a'], 'filters' => []]);
        $racedListBody = json_encode([
            'data' => [['id' => 'pol_raced', 'name' => $policyName]],
            'has_more' => false,
            'next_cursor' => null,
        ]);
        $this->assertIsString($racedListBody);

        $client = $this->buildClient([
            new MockResponse('{"id":"usr_42"}', ['http_code' => 200]),
            // policies.list: not found (first check)
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
            // policies.create: 409 (someone raced us)
            new MockResponse('{"error":{"message":"already exists"}}', ['http_code' => 409]),
            // policies.list: now found (post-race)
            new MockResponse($racedListBody, ['http_code' => 200]),
            // replaceUserPolicies
            new MockResponse('{}', ['http_code' => 200]),
            // embed.createSession
            new MockResponse('{"session_token":"t","expires_in":10,"user_id":"usr_42"}', ['http_code' => 200]),
        ]);

        $result = GetSession::execute($client, [
            'user' => 'ext_99',
            'access' => ['sources' => ['src_a'], 'filters' => []],
        ]);

        $this->assertSame('t', $result->sessionToken);
        // 6 calls: getOrCreate, list, create(409), list-again, replacePolicies, createSession
        $this->assertCount(6, $this->recorded);
    }

    public function testExecutePropagatesOriginAndTtl(): void
    {
        $client = $this->buildClient([
            new MockResponse('{"id":"usr_42"}', ['http_code' => 200]),
            new MockResponse('{"session_token":"t","expires_in":7200,"user_id":"usr_42"}', ['http_code' => 200]),
        ]);

        GetSession::execute($client, [
            'user' => 'ext_99',
            'origin' => 'https://app.example.com',
            'ttl' => 7200,
        ]);

        $sessionBody = $this->recorded[1]['body'] ?? '';
        // HttpClient uses JSON_UNESCAPED_SLASHES, so slashes are NOT escaped
        $this->assertStringContainsString('"origin":"https://app.example.com"', $sessionBody);
        $this->assertStringContainsString('"ttl":7200', $sessionBody);
    }

    public function testExecuteOmitsOriginWhenNotProvided(): void
    {
        $client = $this->buildClient([
            new MockResponse('{"id":"usr_42"}', ['http_code' => 200]),
            new MockResponse('{"session_token":"t","expires_in":3600,"user_id":"usr_42"}', ['http_code' => 200]),
        ]);

        GetSession::execute($client, ['user' => 'ext_99']);

        $sessionBody = $this->recorded[1]['body'] ?? '';
        $this->assertStringNotContainsString('origin', $sessionBody);
    }
}
