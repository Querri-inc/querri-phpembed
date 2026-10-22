<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Exceptions;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Querri\Embed\Exceptions\ApiException;
use Querri\Embed\Exceptions\AuthenticationException;
use Querri\Embed\Exceptions\ConflictException;
use Querri\Embed\Exceptions\ErrorCode;
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
            '422 → Validation' => [422, ValidationException::class],
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

    /**
     * Verbatim 401 captured from the live /v1 server on 2026-10-21: the
     * Stripe-style error sits inside FastAPI's `detail`, and the request id is
     * only in the header (never in the body).
     */
    public function testParsesVerbatimLiveMissingApiKeyResponse(): void
    {
        $raw = '{"detail":{"error":{"type":"authentication_error","code":"missing_api_key",'
            . '"message":"Authorization required. Provide a Bearer qk_ API key or a valid JWT token.",'
            . '"doc_url":"https://docs.querri.com/api/errors#missing_api_key"}}}';
        $body = json_decode($raw, true);
        $headers = ['x-request-id' => ['req_bcd3adccd74d40429159b73182bd6962']];

        try {
            ApiException::raiseForStatus(401, $body, $headers);
            $this->fail('expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame(
                'Authorization required. Provide a Bearer qk_ API key or a valid JWT token.',
                $e->getMessage(),
            );
            $this->assertSame('authentication_error', $e->type);
            $this->assertSame(ErrorCode::MISSING_API_KEY, $e->errorCode);
            $this->assertSame('https://docs.querri.com/api/errors#missing_api_key', $e->docUrl);
            $this->assertSame('req_bcd3adccd74d40429159b73182bd6962', $e->requestId);
            $this->assertNull($e->permission);
            $this->assertNull($e->reason);
        }
    }

    /** Verbatim 403 permission_denied from the live /v1 server (2026-10-21). */
    public function testParsesVerbatimLivePermissionDeniedResponse(): void
    {
        $raw = '{"detail":{"error":{"type":"permission_error","code":"permission_denied",'
            . '"message":"This action needs the dashboards.list.view permission.",'
            . '"permission":"dashboards.list.view","reason":"role_denied",'
            . '"doc_url":"https://docs.querri.com/api/errors#permission_denied"}}}';
        $body = json_decode($raw, true);
        $headers = ['x-permission-denied' => ['dashboards.list.view']];

        try {
            ApiException::raiseForStatus(403, $body, $headers);
            $this->fail('expected PermissionException');
        } catch (PermissionException $e) {
            $this->assertSame('This action needs the dashboards.list.view permission.', $e->getMessage());
            $this->assertSame('permission_error', $e->type);
            $this->assertSame(ErrorCode::PERMISSION_DENIED, $e->errorCode);
            $this->assertSame('dashboards.list.view', $e->permission);
            $this->assertSame(ErrorCode::REASON_ROLE_DENIED, $e->reason);
            $this->assertSame('https://docs.querri.com/api/errors#permission_denied', $e->docUrl);
        }
    }

    public function testPermissionReadFromBodyWinsOverHeader(): void
    {
        $body = ['detail' => ['error' => [
            'code' => 'permission_denied',
            'message' => 'denied',
            'permission' => 'projects.create',
            'reason' => 'not_in_plan',
        ]]];

        $e = ApiException::fromResponse(403, $body, ['x-permission-denied' => ['something.else']]);

        $this->assertSame('projects.create', $e->permission);
        $this->assertSame('not_in_plan', $e->reason);
    }

    public function testPermissionFallsBackToHeaderWhenBodyHasNone(): void
    {
        // e.g. a proxy replaced the JSON body with an HTML page
        $e = ApiException::fromResponse(403, '<html>Forbidden</html>', [
            'x-permission-denied' => ['dashboards.list.view'],
        ]);

        $this->assertSame('dashboards.list.view', $e->permission);
        $this->assertNull($e->reason);
        $this->assertSame('Request failed with status 403', $e->getMessage());
    }

    /**
     * When no single permission applies the server sends an error code in
     * X-Permission-Denied; that must not be read as a permission key.
     */
    public function testPermissionHeaderHoldingAnErrorCodeIsIgnored(): void
    {
        $body = ['detail' => ['error' => [
            'code' => 'permission_denied',
            'reason' => 'scope_exceeds_role',
            'scopes' => ['*'],
        ]]];

        $e = ApiException::fromResponse(403, $body, ['x-permission-denied' => ['permission_denied']]);

        $this->assertNull($e->permission);
        $this->assertSame(ErrorCode::PERMISSION_DENIED, $e->errorCode);
        $this->assertSame(ErrorCode::REASON_SCOPE_EXCEEDS_ROLE, $e->reason);
    }

    public function testPermissionHeaderIsUsedWithNonJsonBody(): void
    {
        $e = ApiException::fromResponse(403, 'not json', ['x-permission-denied' => ['dashboards.list.view']]);
        $this->assertSame('dashboards.list.view', $e->permission);
    }

    /** In-app routes send the code as a string `error` beside permission/reason. */
    public function testParsesInAppFlatPermissionDeniedShape(): void
    {
        $body = ['detail' => [
            'error' => 'permission_denied',
            'permission' => 'org.admin',
            'reason' => 'role_denied',
            'layer' => 'route',
        ]];

        $e = ApiException::fromResponse(403, $body, []);

        $this->assertSame(ErrorCode::PERMISSION_DENIED, $e->errorCode);
        $this->assertSame('org.admin', $e->permission);
        $this->assertSame(ErrorCode::REASON_ROLE_DENIED, $e->reason);
    }

    /** A legacy sentence in a string `error` is a message, not a code. */
    public function testStringErrorSentenceIsNotAnErrorCode(): void
    {
        $e = ApiException::fromResponse(400, ['error' => 'String error message'], []);
        $this->assertNull($e->errorCode);
    }

    /** A top-level legacy `{error: 'forbidden'}` is a message, never a code. */
    public function testTopLevelCodeShapedStringErrorIsNotAnErrorCode(): void
    {
        $e = ApiException::fromResponse(403, ['error' => 'forbidden'], []);

        $this->assertNull($e->errorCode);
        $this->assertSame('forbidden', $e->getMessage());
    }

    /** The in-app flat shape's human `message` is the exception message, not the code. */
    public function testInAppFlatShapeUsesItsMessage(): void
    {
        $body = ['detail' => [
            'error' => 'escalation_denied',
            'message' => 'You cannot grant permissions you do not hold.',
            'missing' => ['org.admin'],
        ]];

        $e = ApiException::fromResponse(403, $body, []);

        $this->assertSame(ErrorCode::ESCALATION_DENIED, $e->errorCode);
        $this->assertSame('You cannot grant permissions you do not hold.', $e->getMessage());
    }

    /** The header names a permission only for permission_denied; other codes never borrow it. */
    public function testPermissionHeaderIgnoredForOtherErrorCodes(): void
    {
        $body = ['detail' => ['error' => [
            'type' => 'permission_error',
            'code' => 'escalation_denied',
            'message' => 'denied',
            'missing' => ['dashboards.list.view'],
        ]]];

        $e = ApiException::fromResponse(403, $body, ['x-permission-denied' => ['dashboards.list.view']]);

        $this->assertSame(ErrorCode::ESCALATION_DENIED, $e->errorCode);
        $this->assertNull($e->permission);
    }

    public function testPermissionHeaderUsedForPermissionDeniedWithoutBodyPermission(): void
    {
        $body = ['detail' => ['error' => ['code' => 'permission_denied', 'message' => 'denied']]];

        $e = ApiException::fromResponse(403, $body, ['x-permission-denied' => ['org.admin']]);

        $this->assertSame('org.admin', $e->permission);
    }

    public function testLastAdminMapsToConflictException(): void
    {
        $body = ['detail' => ['error' => [
            'type' => 'conflict_error',
            'code' => 'last_admin',
            'message' => 'The organization must keep at least one admin.',
        ]]];

        try {
            ApiException::raiseForStatus(409, $body, []);
            $this->fail('expected ConflictException');
        } catch (ConflictException $e) {
            $this->assertSame(ErrorCode::LAST_ADMIN, $e->errorCode);
            $this->assertSame('The organization must keep at least one admin.', $e->getMessage());
        }
    }

    public function testPermissionHeaderAcceptsScalarValue(): void
    {
        $e = ApiException::fromResponse(403, null, ['x-permission-denied' => 'sources.list.view']);
        $this->assertSame('sources.list.view', $e->permission);
    }

    public function testPermissionAndReasonAreNullWhenAbsent(): void
    {
        $e = ApiException::fromResponse(400, ['error' => ['code' => 'x', 'message' => 'm']], []);
        $this->assertNull($e->permission);
        $this->assertNull($e->reason);
    }

    /** Embed role errors carry no header and no permission/reason, only the code. */
    public function testParsesEmbedRoleDisabledError(): void
    {
        $raw = '{"detail":{"error":{"type":"permission_error","code":"role_disabled",'
            . '"message":"That role is a draft; an admin must enable it first.",'
            . '"doc_url":"https://docs.querri.com/api/errors#role_disabled"}}}';

        try {
            ApiException::raiseForStatus(403, json_decode($raw, true), []);
            $this->fail('expected PermissionException');
        } catch (PermissionException $e) {
            $this->assertSame(ErrorCode::ROLE_DISABLED, $e->errorCode);
            $this->assertSame('That role is a draft; an admin must enable it first.', $e->getMessage());
            $this->assertNull($e->permission);
            $this->assertNull($e->reason);
        }
    }

    public function testRoleNotFoundMapsToNotFoundException(): void
    {
        $body = ['detail' => ['error' => [
            'type' => 'not_found_error',
            'code' => 'role_not_found',
            'message' => "No role 'nope' in this organization.",
            'doc_url' => 'https://docs.querri.com/api/errors#role_not_found',
        ]]];

        try {
            ApiException::raiseForStatus(404, $body, []);
            $this->fail('expected NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertSame(ErrorCode::ROLE_NOT_FOUND, $e->errorCode);
            $this->assertSame('not_found_error', $e->type);
        }
    }

    public function testRateLimitExceptionCarriesPermissionAndReason(): void
    {
        $body = ['detail' => ['error' => [
            'code' => 'rate_limit_exceeded',
            'message' => 'slow down',
            'permission' => 'p.key',
            'reason' => 'forbidden',
        ]]];

        try {
            ApiException::raiseForStatus(429, $body, ['retry-after' => ['3'], 'x-permission-denied' => ['h.key']]);
            $this->fail('expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(3.0, $e->retryAfter);
            $this->assertSame('p.key', $e->permission);
            $this->assertSame('forbidden', $e->reason);
            $this->assertSame('rate_limit_exceeded', $e->errorCode);
        }
    }

    public function testRateLimitConstructorStaysPositionallyCompatible(): void
    {
        // Pre-1.1.0 positional call: $retryAfter then $previous. Must still work.
        $prev = new \RuntimeException('root');
        $e = new RateLimitException('m', 429, null, [], 'req', 't', 'c', 'd', 1.5, $prev);

        $this->assertSame(1.5, $e->retryAfter);
        $this->assertSame($prev, $e->getPrevious());
        $this->assertSame('c', $e->errorCode);
        $this->assertNull($e->permission);
    }

    public function testApiExceptionConstructorStaysPositionallyCompatible(): void
    {
        $prev = new \RuntimeException('root');
        $e = new ApiException('m', 403, null, [], 'req', 't', 'c', 'd', $prev);

        $this->assertSame($prev, $e->getPrevious());
        $this->assertNull($e->permission);
        $this->assertNull($e->reason);
    }

    public function testErrorCodeConstantsMatchWireValues(): void
    {
        $constants = (new \ReflectionClass(ErrorCode::class))->getConstants();
        foreach ($constants as $name => $value) {
            $expected = strtolower(preg_replace('/^REASON_/', '', $name) ?? $name);
            $this->assertSame($expected, $value, "ErrorCode::{$name}");
        }
        $this->assertSame('role_not_allowed', ErrorCode::ROLE_NOT_ALLOWED);
        $this->assertSame('seat_change_required', ErrorCode::SEAT_CHANGE_REQUIRED);
        $this->assertSame('insufficient_scope', ErrorCode::INSUFFICIENT_SCOPE);
    }
}
