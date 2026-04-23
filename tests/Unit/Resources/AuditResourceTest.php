<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Resources;

use Querri\Embed\Tests\Unit\MockHttpTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AuditResourceTest extends MockHttpTestCase
{
    public function testListEventsGetsWithFilters(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);

        $client->audit->listEvents(['action' => 'user.create', 'limit' => 10]);

        $this->assertSame('GET', $this->recorded[0]['method']);
        $this->assertStringContainsString('/audit/events', $this->recorded[0]['url']);
        $this->assertStringContainsString('action=user.create', $this->recorded[0]['url']);
    }

    public function testListEventsWithNullParams(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);

        $client->audit->listEvents();

        $this->assertSame('GET', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/audit/events', $this->recorded[0]['url']);
    }
}
