<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Querri\Embed\Http\RetryStrategy;

final class RetryStrategyTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function idempotentMethodCases(): array
    {
        return [
            'GET' => ['GET', true],
            'PUT' => ['PUT', true],
            'DELETE' => ['DELETE', true],
            'HEAD' => ['HEAD', true],
            'OPTIONS' => ['OPTIONS', true],
            'POST' => ['POST', false],
            'PATCH' => ['PATCH', false],
            'lowercase get' => ['get', true],
            'mixed-case Patch' => ['Patch', false],
        ];
    }

    #[DataProvider('idempotentMethodCases')]
    public function testIsIdempotent(string $method, bool $expected): void
    {
        $this->assertSame($expected, RetryStrategy::isIdempotent($method));
    }

    public function testShouldRetry429AlwaysTrue(): void
    {
        $this->assertTrue(RetryStrategy::shouldRetry(429, true));
        $this->assertTrue(RetryStrategy::shouldRetry(429, false));
    }

    /** @return array<string, array{int, bool, bool}> */
    public static function shouldRetryCases(): array
    {
        return [
            '500 idempotent' => [500, true, true],
            '500 non-idempotent' => [500, false, false],
            '502 idempotent' => [502, true, true],
            '503 idempotent' => [503, true, true],
            '504 idempotent' => [504, true, true],
            '502 non-idempotent' => [502, false, false],
            '400 idempotent' => [400, true, false],
            '404 idempotent' => [404, true, false],
            '200 idempotent' => [200, true, false],
        ];
    }

    #[DataProvider('shouldRetryCases')]
    public function testShouldRetry(int $status, bool $isIdempotent, bool $expected): void
    {
        $this->assertSame($expected, RetryStrategy::shouldRetry($status, $isIdempotent));
    }

    public function testCalculateDelayReturnsPositiveIntForFirstRetry(): void
    {
        // attempt 1: exponential = 500 * 2^0 = 500ms; ±25% jitter → roughly 375–625
        $delay = RetryStrategy::calculateDelay(1);
        $this->assertGreaterThan(0, $delay);
        $this->assertLessThanOrEqual(30_000, $delay);
    }

    public function testCalculateDelayCapsAtMaxDelay(): void
    {
        // attempt 20 would be 500 * 2^19 = 262M ms without the cap
        $delay = RetryStrategy::calculateDelay(20);
        $this->assertLessThanOrEqual(30_000, $delay);
    }

    public function testCalculateDelayRespectsRetryAfterWhenLarger(): void
    {
        // attempt 1 yields ~500ms; Retry-After of 2s should win
        $delay = RetryStrategy::calculateDelay(1, retryAfterSeconds: 2.0);
        $this->assertGreaterThanOrEqual(2_000, $delay);
    }

    public function testCalculateDelayIgnoresZeroRetryAfter(): void
    {
        // retryAfterSeconds <= 0 should not override the exponential delay
        $delay = RetryStrategy::calculateDelay(1, retryAfterSeconds: 0.0);
        $this->assertLessThanOrEqual(30_000, $delay);
    }

    public function testGetRetryAfterHandlesArrayHeader(): void
    {
        $this->assertSame(5.0, RetryStrategy::getRetryAfter(['retry-after' => ['5']]));
    }

    public function testGetRetryAfterHandlesScalarHeader(): void
    {
        $this->assertSame(5.0, RetryStrategy::getRetryAfter(['retry-after' => '5']));
    }

    public function testGetRetryAfterReturnsNullWhenMissing(): void
    {
        $this->assertNull(RetryStrategy::getRetryAfter([]));
    }

    public function testGetRetryAfterReturnsNullOnNonNumericValue(): void
    {
        $this->assertNull(RetryStrategy::getRetryAfter(['retry-after' => ['garbage']]));
    }

    public function testGetRetryAfterHandlesFractionalSeconds(): void
    {
        $this->assertSame(1.5, RetryStrategy::getRetryAfter(['retry-after' => '1.5']));
    }
}
