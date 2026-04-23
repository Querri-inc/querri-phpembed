<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Exceptions;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Querri\Embed\Exceptions\ApiException;
use Querri\Embed\Exceptions\AuthenticationException;
use Querri\Embed\Exceptions\ConflictException;
use Querri\Embed\Exceptions\NotFoundException;
use Querri\Embed\Exceptions\PermissionException;
use Querri\Embed\Exceptions\RateLimitException;
use Querri\Embed\Exceptions\ServerException;
use Querri\Embed\Exceptions\ValidationException;

final class ApiExceptionTest extends TestCase
{
    public function testRaiseForStatusIsNoopBelow400(): void
    {
        ApiException::raiseForStatus(200, null, []);
        ApiException::raiseForStatus(204, null, []);
        ApiException::raiseForStatus(399, null, []);
        $this->assertTrue(true); // reached without exception
    }

    /** @return array<string, array{int, class-string<ApiException>}> */
    public static function statusToExceptionCases(): array
    {
        return [
            '400 → Validation' => [400, ValidationException::class],
            '401 → Authentication' => [401, AuthenticationException::class],
            '403 → Permission' => [403, PermissionException::class],
            '404 → NotFound' => [404, NotFoundException::class],
            '409 → Conflict' => [409, ConflictException::class],
            '429 → RateLimit' => [429, RateLimitException::class],
            '500 → Server' => [500, ServerException::class],
            '502 → Server' => [502, ServerException::class],
            '599 → Server' => [599, ServerException::class],
            '418 → generic ApiException' => [418, ApiException::class],
        ];
    }

    /** @param class-string<ApiException> $expected */
    #[DataProvider('statusToExceptionCases')]
    public function testRaiseForStatusDispatchesToSubclass(int $status, string $expected): void
    {
        $this->expectException($expected);
        ApiException::raiseForStatus($status, null, []);
    }

    public function testFromResponseExtractsStripeStyleNestedError(): void
    {
        $body = [
            'error' => [
                'type' => 'invalid_request_error',
                'code' => 'parameter_missing',
                'message' => 'The user_id parameter is required.',
                'doc_url' => 'https://docs.querri.com/errors/parameter_missing',
                'request_id' => 'req_abc123',
            ],
        ];

        $exception = ApiException::fromResponse(400, $body, []);

        $this->assertSame(400, $exception->status);
        $this->assertSame('The user_id parameter is required.', $exception->getMessage());
        $this->assertSame('invalid_request_error', $exception->type);
        $this->assertSame('parameter_missing', $exception->errorCode);
        $this->assertSame('https://docs.querri.com/errors/parameter_missing', $exception->docUrl);
        $this->assertSame('req_abc123', $exception->requestId);
        $this->assertSame($body, $exception->body);
    }

    public function testFromResponsePrefersHeaderRequestIdOverBody(): void
    {
        $body = [
            'error' => [
                'message' => 'bad',
                'request_id' => 'body_req_id',
            ],
        ];
        $headers = ['x-request-id' => ['header_req_id']];

        $exception = ApiException::fromResponse(400, $body, $headers);

        $this->assertSame('header_req_id', $exception->requestId);
    }

    public function testFromResponseReadsScalarRequestIdHeader(): void
    {
        $exception = ApiException::fromResponse(500, null, ['x-request-id' => 'scalar_req_id']);
        $this->assertSame('scalar_req_id', $exception->requestId);
    }

    public function testFromResponseDefaultsMessageWhenBodyIsNull(): void
    {
        $exception = ApiException::fromResponse(502, null, []);
        $this->assertSame('Request failed with status 502', $exception->getMessage());
        $this->assertNull($exception->type);
        $this->assertNull($exception->errorCode);
        $this->assertNull($exception->docUrl);
    }

    public function testFromResponseFallsBackToFlatMessage(): void
    {
        $body = ['message' => 'Plain flat message'];
        $exception = ApiException::fromResponse(400, $body, []);
        $this->assertSame('Plain flat message', $exception->getMessage());
    }

    public function testFromResponseFallsBackToStringError(): void
    {
        // Legacy: some endpoints return {"error": "string"} instead of a nested object.
        $body = ['error' => 'String error message'];
        $exception = ApiException::fromResponse(400, $body, []);
        $this->assertSame('String error message', $exception->getMessage());
    }

    public function testFromResponseHandlesNonArrayBody(): void
    {
        // e.g. a gateway returned plain HTML or a string
        $exception = ApiException::fromResponse(502, 'Gateway Timeout HTML page', []);
        $this->assertSame('Request failed with status 502', $exception->getMessage());
    }

    public function testRaiseForStatus429PopulatesRetryAfter(): void
    {
        try {
            ApiException::raiseForStatus(429, null, ['retry-after' => ['7']]);
            $this->fail('expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(7.0, $e->retryAfter);
        }
    }

    public function testRaiseForStatus429WithoutRetryAfterHeader(): void
    {
        try {
            ApiException::raiseForStatus(429, null, []);
            $this->fail('expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertNull($e->retryAfter);
        }
    }

    public function testUnwrapsFastApiHttpExceptionWithStripeError(): void
    {
        // Querri API wraps known errors in FastAPI's HTTPException detail envelope
        // around a Stripe-shaped error object.
        $body = [
            'detail' => [
                'error' => [
                    'type' => 'authentication_error',
                    'code' => 'invalid_api_key',
                    'message' => 'The API key provided is invalid, expired, or revoked.',
                    'doc_url' => 'https://docs.querri.com/api/errors#invalid_api_key',
                    'request_id' => 'req_fastapi_wrap',
                ],
            ],
        ];

        $exception = ApiException::fromResponse(401, $body, []);

        $this->assertSame('The API key provided is invalid, expired, or revoked.', $exception->getMessage());
        $this->assertSame('authentication_error', $exception->type);
        $this->assertSame('invalid_api_key', $exception->errorCode);
        $this->assertSame('https://docs.querri.com/api/errors#invalid_api_key', $exception->docUrl);
        $this->assertSame('req_fastapi_wrap', $exception->requestId);
    }

    public function testUnwrapsFastApiHttpExceptionFlat(): void
    {
        // FastAPI HTTPException with the error fields directly on detail (no inner 'error' key).
        $body = [
            'detail' => [
                'type' => 'invalid_request_error',
                'code' => 'source_not_found',
                'message' => 'Could not resolve source(s): foo.csv.',
                'doc_url' => 'https://docs.querri.com/api/errors#source_not_found',
            ],
        ];

        $exception = ApiException::fromResponse(400, $body, []);

        $this->assertSame('Could not resolve source(s): foo.csv.', $exception->getMessage());
        $this->assertSame('invalid_request_error', $exception->type);
        $this->assertSame('source_not_found', $exception->errorCode);
        $this->assertSame('https://docs.querri.com/api/errors#source_not_found', $exception->docUrl);
    }

    public function testUnwrapsFastApiValidationSingleError(): void
    {
        // Pydantic validation error shape.
        $body = [
            'detail' => [
                [
                    'type' => 'missing',
                    'loc' => ['body', 'user_id'],
                    'msg' => 'Field required',
                    'input' => [],
                ],
            ],
        ];

        $exception = ApiException::fromResponse(422, $body, []);

        $this->assertSame('Validation failed: body.user_id: Field required', $exception->getMessage());
        $this->assertSame('missing', $exception->type);
    }

    public function testUnwrapsFastApiValidationMultipleErrors(): void
    {
        $body = [
            'detail' => [
                ['type' => 'missing', 'loc' => ['body', 'a'], 'msg' => 'A required'],
                ['type' => 'value_error', 'loc' => ['body', 'b'], 'msg' => 'B invalid'],
            ],
        ];

        $exception = ApiException::fromResponse(422, $body, []);

        $message = $exception->getMessage();
        $this->assertStringContainsString('body.a: A required', $message);
        $this->assertStringContainsString('body.b: B invalid', $message);
        $this->assertStringContainsString(';', $message);
        // Type taken from the first validation error.
        $this->assertSame('missing', $exception->type);
    }

    public function testUnwrapsFastApiPlainStringDetail(): void
    {
        $body = ['detail' => 'Something bad happened'];

        $exception = ApiException::fromResponse(500, $body, []);

        $this->assertSame('Something bad happened', $exception->getMessage());
    }

    public function testBodyPropertyPreservesOriginalWrappedShape(): void
    {
        // The unwrap is only used to extract metadata; the exception's body property
        // must still reflect exactly what the server sent, so callers can inspect
        // the raw response when debugging.
        $body = [
            'detail' => [
                'error' => [
                    'type' => 'invalid_request_error',
                    'code' => 'some_code',
                    'message' => 'human readable',
                ],
            ],
        ];

        $exception = ApiException::fromResponse(400, $body, []);

        $this->assertSame($body, $exception->body);
        $this->assertArrayHasKey('detail', $exception->body);
    }
}
