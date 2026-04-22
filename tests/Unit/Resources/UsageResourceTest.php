<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Resources;

use Querri\Embed\Tests\Unit\MockHttpTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class UsageResourceTest extends MockHttpTestCase
{
    public function testGetOrgUsageDefaultsCurrentMonth(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->usage->getOrgUsage();

        $this->assertStringContainsString('/usage?period=current_month', $this->recorded[0]['url']);
    }

    public function testGetOrgUsageAcceptsPreferredArrayParams(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->usage->getOrgUsage(['period' => 'last_30_days']);

        $this->assertStringContainsString('period=last_30_days', $this->recorded[0]['url']);
    }

    public function testGetOrgUsageAcceptsLegacyStringPeriod(): void
    {
        // Deprecated path — retained until 0.3.0
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->usage->getOrgUsage('last_30_days');

        $this->assertStringContainsString('period=last_30_days', $this->recorded[0]['url']);
    }

    public function testGetUserUsageEncodesIdAndPeriod(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->usage->getUserUsage('u/1', ['period' => 'last_month']);

        $this->assertStringContainsString('/usage/users/u%2F1', $this->recorded[0]['url']);
        $this->assertStringContainsString('period=last_month', $this->recorded[0]['url']);
    }

    public function testGetUserUsageLegacyStringStillWorks(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->usage->getUserUsage('u_1', 'current_month');

        $this->assertStringContainsString('period=current_month', $this->recorded[0]['url']);
    }

    public function testGetUserUsageDefaultsWhenNoArg(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->usage->getUserUsage('u_1');

        $this->assertStringContainsString('period=current_month', $this->recorded[0]['url']);
    }
}
