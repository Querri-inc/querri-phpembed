<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit;

use Querri\Embed\Config;
use Querri\Embed\QuerriClient;
use Querri\Embed\Resources\AuditResource;
use Querri\Embed\Resources\ChatsResource;
use Querri\Embed\Resources\DashboardsResource;
use Querri\Embed\Resources\DataResource;
use Querri\Embed\Resources\EmbedResource;
use Querri\Embed\Resources\FilesResource;
use Querri\Embed\Resources\KeysResource;
use Querri\Embed\Resources\PoliciesResource;
use Querri\Embed\Resources\ProjectsResource;
use Querri\Embed\Resources\SharingResource;
use Querri\Embed\Resources\SourcesResource;
use Querri\Embed\Resources\UsageResource;
use Querri\Embed\Resources\UsersResource;
use Querri\Embed\Session\GetSessionResult;
use Querri\Embed\UserQuerriClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class QuerriClientTest extends MockHttpTestCase
{
    public function testConstructFromApiKeyString(): void
    {
        $client = new QuerriClient('qk_abc');
        $this->assertInstanceOf(UsersResource::class, $client->users);
    }

    public function testConstructFromArrayConfig(): void
    {
        $client = new QuerriClient(['api_key' => 'qk_abc', 'org_id' => 'org_x']);
        $this->assertInstanceOf(UsersResource::class, $client->users);
    }

    public function testConstructFromArrayAcceptsCamelCaseKeys(): void
    {
        $client = new QuerriClient(['apiKey' => 'qk_abc', 'orgId' => 'org_x', 'maxRetries' => 2]);
        $this->assertInstanceOf(UsersResource::class, $client->users);
    }

    public function testConstructFromConfigObject(): void
    {
        $config = Config::resolve(apiKey: 'qk_abc');
        $client = new QuerriClient($config);
        $this->assertInstanceOf(UsersResource::class, $client->users);
    }

    /** @return array<string, array{string, class-string}> */
    public static function resourceCases(): array
    {
        return [
            'users' => ['users', UsersResource::class],
            'embed' => ['embed', EmbedResource::class],
            'policies' => ['policies', PoliciesResource::class],
            'dashboards' => ['dashboards', DashboardsResource::class],
            'projects' => ['projects', ProjectsResource::class],
            'chats' => ['chats', ChatsResource::class],
            'data' => ['data', DataResource::class],
            'sources' => ['sources', SourcesResource::class],
            'files' => ['files', FilesResource::class],
            'keys' => ['keys', KeysResource::class],
            'audit' => ['audit', AuditResource::class],
            'usage' => ['usage', UsageResource::class],
            'sharing' => ['sharing', SharingResource::class],
        ];
    }

    /**
     * @param class-string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('resourceCases')]
    public function testLazyLoadsEachResource(string $property, string $expected): void
    {
        $client = new QuerriClient('qk_abc');
        $this->assertInstanceOf($expected, $client->$property);
    }

    public function testLazyLoadReturnsSameInstanceOnRepeatedAccess(): void
    {
        $client = new QuerriClient('qk_abc');
        $first = $client->users;
        $second = $client->users;
        $this->assertSame($first, $second);
    }

    public function testUnknownPropertyThrowsError(): void
    {
        $client = new QuerriClient('qk_abc');
        $this->expectException(\Error::class);
        /** @phpstan-ignore property.notFound (intentionally testing unknown property dispatch) */
        $_ = $client->nonexistent;
    }

    public function testAsUserReturnsUserQuerriClient(): void
    {
        $parent = new QuerriClient('qk_abc');
        $session = new GetSessionResult(
            sessionToken: 'tok_abc',
            expiresIn: 3600,
            userId: 'u_1',
        );

        $user = $parent->asUser($session);

        $this->assertInstanceOf(UserQuerriClient::class, $user);
    }

    public function testGetSessionDelegatesToGetSessionExecute(): void
    {
        $client = $this->makeQuerriClient([
            // users.getOrCreate
            new MockResponse('{"id":"u_1","external_id":"ext_99"}', ['http_code' => 200]),
            // embed.createSession
            new MockResponse(
                '{"session_token":"tok_abc","expires_in":3600,"user_id":"u_1"}',
                ['http_code' => 200],
            ),
        ]);

        $result = $client->getSession(['user' => 'ext_99']);

        $this->assertSame('tok_abc', $result->sessionToken);
        $this->assertSame('u_1', $result->userId);
    }
}
