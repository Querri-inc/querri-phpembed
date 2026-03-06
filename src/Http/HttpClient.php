<?php

declare(strict_types=1);

namespace Querri\Embed\Http;

use Querri\Embed\Config;
use Querri\Embed\Exceptions\ApiException;
use Querri\Embed\Exceptions\ConnectionException;
use Querri\Embed\Exceptions\TimeoutException;
use Symfony\Component\HttpClient\Exception\TimeoutException as SymfonyTimeoutException;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpClient
{
    private readonly HttpClientInterface $client;
    private readonly Config $config;

    public function __construct(Config $config, ?HttpClientInterface $client = null)
    {
        $this->config = $config;
        $this->client = $client ?? SymfonyHttpClient::create([
            'timeout' => $config->timeout,
        ]);
    }

    /**
     * @param array{
     *   method: string,
     *   path: string,
     *   body?: array|null,
     *   query?: array<string, string|int|bool|null>|null,
     *   headers?: array<string, string>|null,
     *   timeout?: float|null,
     *   idempotent?: bool|null,
     *   maxRetries?: int|null,
     * } $options
     * @return array<string, mixed> Decoded JSON response
     *
     * @throws ApiException
     * @throws ConnectionException
     * @throws TimeoutException
     */
    public function request(array $options): array
    {
        $method = strtoupper($options['method']);
        $path = $options['path'];
        $body = $options['body'] ?? null;
        $query = $options['query'] ?? null;
        $extraHeaders = $options['headers'] ?? null;
        $timeout = $options['timeout'] ?? $this->config->timeout;
        $isIdempotent = $options['idempotent'] ?? RetryStrategy::isIdempotent($method);
        $maxRetries = $options['maxRetries'] ?? $this->config->maxRetries;

        $url = $this->buildUrl($path, $query);
        $headers = $this->buildHeaders($extraHeaders, $body);

        $lastError = null;

        // Retry loop: first attempt ($attempt=0) + up to maxRetries retries
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            // Delay before retry, respecting Retry-After header if present
            if ($attempt > 0 && $lastError !== null) {
                $retryAfter = null;
                if ($lastError instanceof ApiException) {
                    $retryAfter = RetryStrategy::getRetryAfter($lastError->headers);
                }
                $delay = RetryStrategy::calculateDelay($attempt, $retryAfter);
                usleep($delay * 1000);
            }

            try {
                $requestOptions = [
                    'headers' => $headers,
                    'timeout' => $timeout,
                ];

                if ($body !== null) {
                    $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES;
                    if ($body === []) {
                        $flags |= JSON_FORCE_OBJECT;
                    }
                    $requestOptions['body'] = json_encode($body, $flags);
                }

                $response = $this->client->request($method, $url, $requestOptions);
                $statusCode = $response->getStatusCode();
                // false = don't throw on error status codes; we handle them ourselves
                $responseHeaders = $response->getHeaders(false);

                if ($statusCode >= 400) {
                    $responseBody = null;
                    try {
                        $responseBody = $response->toArray(false);
                    } catch (\Throwable) {
                        // Response may not be JSON
                    }

                    // Retryable errors: store and continue loop; otherwise throw immediately
                    if (RetryStrategy::shouldRetry($statusCode, $isIdempotent) && $attempt < $maxRetries) {
                        $lastError = ApiException::fromResponse($statusCode, $responseBody, $responseHeaders);
                        continue;
                    }

                    ApiException::raiseForStatus($statusCode, $responseBody, $responseHeaders);
                }

                if ($statusCode === 204) {
                    return [];
                }

                // Wrap JSON decode failures on success responses (e.g., gateway returning HTML)
                try {
                    return $response->toArray(false);
                } catch (\JsonException $e) {
                    throw new ConnectionException(
                        "Invalid JSON response from API: {$method} {$path}",
                        previous: $e,
                    );
                }
            } catch (ApiException $e) {
                // Re-throw immediately — already mapped to specific exception types
                throw $e;
            } catch (SymfonyTimeoutException $e) {
                // Catch timeout before general TransportException (subclass must come first)
                if ($attempt < $maxRetries && $isIdempotent) {
                    $lastError = new TimeoutException(
                        "Request timed out after {$timeout}s: {$method} {$path}",
                        previous: $e,
                    );
                    continue;
                }
                throw new TimeoutException(
                    "Request timed out after {$timeout}s: {$method} {$path}",
                    previous: $e,
                );
            } catch (TransportExceptionInterface $e) {
                // Connection errors: retry if idempotent, throw immediately for POST/PATCH
                if ($attempt < $maxRetries && $isIdempotent) {
                    $lastError = new ConnectionException(
                        "Connection failed: {$e->getMessage()}",
                        previous: $e,
                    );
                    continue;
                }

                throw new ConnectionException(
                    "Connection failed: {$e->getMessage()}",
                    previous: $e,
                );
            }
        }

        if ($lastError !== null) {
            throw $lastError;
        }

        throw new ConnectionException('Request failed after all retries');
    }

    private function buildUrl(string $path, ?array $query): string
    {
        $url = rtrim($this->config->baseUrl, '/') . $path;

        if ($query !== null) {
            $filtered = array_filter($query, fn ($v) => $v !== null);
            if ($filtered !== []) {
                $url .= '?' . http_build_query($filtered);
            }
        }

        return $url;
    }

    private function buildHeaders(?array $extra, mixed $body): array
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => $this->config->userAgent,
        ];

        if ($this->config->sessionToken !== null) {
            $headers['X-Embed-Session'] = $this->config->sessionToken;
        } else {
            $headers['Authorization'] = "Bearer {$this->config->apiKey}";
            if ($this->config->orgId !== null) {
                $headers['X-Tenant-ID'] = $this->config->orgId;
            }
        }

        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        if ($extra !== null) {
            $headers = array_merge($headers, $extra);
        }

        return $headers;
    }
}
