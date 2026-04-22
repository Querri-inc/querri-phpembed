<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Querri\Embed\Config;
use Querri\Embed\Http\HttpClient;
use Querri\Embed\QuerriClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Base test case for any test that needs to drive an HttpClient or QuerriClient
 * via Symfony's MockHttpClient. Records outbound requests into $this->recorded
 * so tests can assert on method, URL, headers, and body.
 */
abstract class MockHttpTestCase extends TestCase
{
    /** @var list<array{method: string, url: string, body: ?string, headers: array<string, string>}> */
    protected array $recorded = [];

    /**
     * Build a MockHttpClient that serves $responses in order and records each
     * outbound request into $this->recorded.
     *
     * @param list<MockResponse|\Throwable> $responses
     */
    protected function makeMockTransport(array $responses): HttpClientInterface
    {
        $this->recorded = [];
        $recorded = &$this->recorded;
        $iterator = new \ArrayIterator($responses);

        return new MockHttpClient(
            function (string $method, string $url, array $options) use (&$recorded, $iterator) {
                $body = null;
                if (isset($options['body']) && is_string($options['body'])) {
                    $body = $options['body'];
                }
                $recorded[] = [
                    'method' => $method,
                    'url' => $url,
                    'body' => $body,
                    'headers' => self::normalizeHeaders($options['headers'] ?? []),
                ];
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
    }

    /**
     * Build a QuerriClient wired to a mocked transport.
     *
     * @param list<MockResponse|\Throwable> $responses
     */
    protected function makeQuerriClient(array $responses, ?Config $config = null): QuerriClient
    {
        return new QuerriClient(
            $config ?? $this->defaultConfig(),
            $this->makeMockTransport($responses),
        );
    }

    /**
     * Build a bare HttpClient wired to a mocked transport.
     *
     * @param list<MockResponse|\Throwable> $responses
     */
    protected function makeHttpClient(array $responses, ?Config $config = null): HttpClient
    {
        return new HttpClient(
            $config ?? $this->defaultConfig(),
            $this->makeMockTransport($responses),
        );
    }

    protected function defaultConfig(): Config
    {
        return Config::resolve(
            apiKey: 'test_key',
            orgId: 'org_test',
            host: 'https://example.com',
            maxRetries: 0, // no retries in smoke tests — keeps them fast
        );
    }

    /**
     * Symfony's callable MockHttpClient passes headers as a list<string>
     * in "Name: Value" form. Convert to a lowercase-keyed assoc array.
     *
     * @param iterable<mixed>|string $raw
     * @return array<string, string>
     */
    private static function normalizeHeaders(iterable|string $raw): array
    {
        $out = [];
        if (is_string($raw)) {
            return $out;
        }
        foreach ($raw as $line) {
            if (!is_string($line) || !str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $out[strtolower(trim($name))] = trim($value);
        }
        return $out;
    }
}
