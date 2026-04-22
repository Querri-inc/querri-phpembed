<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Session;

use PHPUnit\Framework\TestCase;
use Querri\Embed\Session\GetSessionResult;

final class GetSessionResultTest extends TestCase
{
    public function testJsonSerializeReturnsExpectedShape(): void
    {
        $result = new GetSessionResult(
            sessionToken: 'tok_abc',
            expiresIn: 3600,
            userId: 'usr_42',
            externalId: 'ext_99',
        );

        $this->assertSame([
            'session_token' => 'tok_abc',
            'expires_in' => 3600,
            'user_id' => 'usr_42',
            'external_id' => 'ext_99',
        ], $result->jsonSerialize());
    }

    public function testToArrayMatchesJsonSerialize(): void
    {
        $result = new GetSessionResult('t', 10, 'u', null);
        $this->assertSame($result->jsonSerialize(), $result->toArray());
    }

    public function testJsonEncodeRoundTrips(): void
    {
        $result = new GetSessionResult('tok_abc', 3600, 'usr_42');
        $json = json_encode($result);
        $this->assertIsString($json);

        $decoded = json_decode($json, true);
        $this->assertSame([
            'session_token' => 'tok_abc',
            'expires_in' => 3600,
            'user_id' => 'usr_42',
            'external_id' => null,
        ], $decoded);
    }

    public function testExternalIdDefaultsToNull(): void
    {
        $result = new GetSessionResult('t', 10, 'u');
        $this->assertNull($result->externalId);
    }
}
