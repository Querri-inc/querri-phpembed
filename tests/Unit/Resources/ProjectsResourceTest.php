<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit\Resources;

use Querri\Embed\Tests\Unit\MockHttpTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ProjectsResourceTest extends MockHttpTestCase
{
    public function testListGetsProjects(): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse('{"data":[],"has_more":false,"next_cursor":null}', ['http_code' => 200]),
        ]);

        $client->projects->list(['user_id' => 'u_1']);

        $this->assertSame('GET', $this->recorded[0]['method']);
        $this->assertStringContainsString('/projects?user_id=u_1', $this->recorded[0]['url']);
    }

    public function testCreatePostsProject(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->projects->create(['name' => 'Q1 analysis', 'user_id' => 'u_1']);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/projects', $this->recorded[0]['url']);
    }

    public function testRetrieveGetsProject(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->projects->retrieve('p/slashed');

        $this->assertStringContainsString('p%2Fslashed', $this->recorded[0]['url']);
    }

    public function testUpdatePutsProject(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->projects->update('p_1', ['name' => 'renamed']);

        $this->assertSame('PUT', $this->recorded[0]['method']);
    }

    public function testRunPostsUserId(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->projects->run('p_1', ['user_id' => 'u_1']);

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/projects/p_1/run', $this->recorded[0]['url']);
        $this->assertSame('{"user_id":"u_1"}', $this->recorded[0]['body']);
    }

    public function testRunStatusGets(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->projects->runStatus('p_1');

        $this->assertSame('GET', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/projects/p_1/run/status', $this->recorded[0]['url']);
    }

    public function testRunCancelPosts(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->projects->runCancel('p_1');

        $this->assertSame('POST', $this->recorded[0]['method']);
        $this->assertStringEndsWith('/projects/p_1/run/cancel', $this->recorded[0]['url']);
    }

    public function testListStepsGets(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->projects->listSteps('p_1');

        $this->assertStringEndsWith('/projects/p_1/steps', $this->recorded[0]['url']);
    }

    public function testGetStepDataEncodesBothIds(): void
    {
        $client = $this->makeQuerriClient([new MockResponse('{}', ['http_code' => 200])]);

        $client->projects->getStepData('p/1', 's/1');

        $this->assertStringContainsString('/projects/p%2F1/steps/s%2F1/data', $this->recorded[0]['url']);
    }
}
