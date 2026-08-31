<?php

declare(strict_types=1);

namespace Querri\Embed\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Querri\Embed\QuerriClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Asserts that every path+method the SDK actually builds appears in the
 * contract snapshot (tests/fixtures/openapi.snapshot.json). The scheduled
 * contract workflow then checks the snapshot against the live openapi.json,
 * so together they catch SDK↔server drift in both directions.
 *
 * Each case drives the real resource method through the mock transport and
 * records the URL it produced — the snapshot is compared against observed
 * behavior, not against a second hand-written list of paths.
 */
final class ContractSurfaceTest extends MockHttpTestCase
{
    private const ID_A = 'CONTRACT-ID-A';
    private const ID_B = 'CONTRACT-ID-B';

    /** Generic response body satisfying every resource's response handling. */
    private const BODY = '{"data":[],"has_more":false,"next_cursor":null,"id":"x","columns":[]}';

    /** @return array<string, array<int|string, string[]>> */
    private static function snapshotPaths(): array
    {
        $raw = file_get_contents(__DIR__ . '/../fixtures/openapi.snapshot.json');
        assert($raw !== false);
        $snapshot = json_decode($raw, true);
        assert(is_array($snapshot) && is_array($snapshot['paths']));

        return $snapshot['paths'];
    }

    /**
     * Every public SDK call that hits the API, as [invoker] pairs. IDs use
     * the sentinel constants so the recorded path can be normalized back to
     * a template.
     *
     * @return array<string, array{callable(QuerriClient): mixed}>
     */
    public static function surfaceCases(): array
    {
        $a = self::ID_A;
        $b = self::ID_B;

        $calls = [
            // Users
            'users.create' => fn (QuerriClient $c) => $c->users->create(['external_id' => 'e']),
            'users.retrieve' => fn (QuerriClient $c) => $c->users->retrieve($a),
            'users.list' => fn (QuerriClient $c) => $c->users->list(),
            'users.update' => fn (QuerriClient $c) => $c->users->update($a, ['email' => 'e@x.com']),
            'users.del' => fn (QuerriClient $c) => $c->users->del($a),
            'users.removeExternalId' => fn (QuerriClient $c) => $c->users->removeExternalId($a),
            'users.getOrCreate' => fn (QuerriClient $c) => $c->users->getOrCreate($a),
            // Embed sessions
            'embed.createSession' => fn (QuerriClient $c) => $c->embed->createSession(['user_id' => 'u']),
            'embed.refreshSession' => fn (QuerriClient $c) => $c->embed->refreshSession('tok'),
            'embed.listSessions' => fn (QuerriClient $c) => $c->embed->listSessions(),
            'embed.revokeSession' => fn (QuerriClient $c) => $c->embed->revokeSession($a),
            // Access policies
            'policies.create' => fn (QuerriClient $c) => $c->policies->create(['name' => 'p']),
            'policies.retrieve' => fn (QuerriClient $c) => $c->policies->retrieve($a),
            'policies.list' => fn (QuerriClient $c) => $c->policies->list(),
            'policies.update' => fn (QuerriClient $c) => $c->policies->update($a, ['name' => 'p']),
            'policies.del' => fn (QuerriClient $c) => $c->policies->del($a),
            'policies.assignUsers' => fn (QuerriClient $c) => $c->policies->assignUsers($a, ['user_ids' => ['u']]),
            'policies.removeUser' => fn (QuerriClient $c) => $c->policies->removeUser($a, $b),
            'policies.replaceUserPolicies' => fn (QuerriClient $c) => $c->policies->replaceUserPolicies($a, ['policy_ids' => ['p']]),
            'policies.resolveAccess' => fn (QuerriClient $c) => $c->policies->resolveAccess('u', 's'),
            'policies.listColumns' => fn (QuerriClient $c) => $c->policies->listColumns('s'),
            // Dashboards
            'dashboards.list' => fn (QuerriClient $c) => $c->dashboards->list(),
            'dashboards.create' => fn (QuerriClient $c) => $c->dashboards->create(['name' => 'd']),
            'dashboards.retrieve' => fn (QuerriClient $c) => $c->dashboards->retrieve($a),
            'dashboards.update' => fn (QuerriClient $c) => $c->dashboards->update($a, ['name' => 'd']),
            'dashboards.del' => fn (QuerriClient $c) => $c->dashboards->del($a),
            'dashboards.refresh' => fn (QuerriClient $c) => $c->dashboards->refresh($a),
            'dashboards.refreshStatus' => fn (QuerriClient $c) => $c->dashboards->refreshStatus($a),
            // Projects
            'projects.list' => fn (QuerriClient $c) => $c->projects->list(),
            'projects.create' => fn (QuerriClient $c) => $c->projects->create(['name' => 'p']),
            'projects.retrieve' => fn (QuerriClient $c) => $c->projects->retrieve($a),
            'projects.update' => fn (QuerriClient $c) => $c->projects->update($a, ['name' => 'p']),
            'projects.del' => fn (QuerriClient $c) => $c->projects->del($a),
            'projects.run' => fn (QuerriClient $c) => $c->projects->run($a, []),
            'projects.runStatus' => fn (QuerriClient $c) => $c->projects->runStatus($a),
            'projects.runCancel' => fn (QuerriClient $c) => $c->projects->runCancel($a),
            'projects.listSteps' => fn (QuerriClient $c) => $c->projects->listSteps($a),
            'projects.getStepData' => fn (QuerriClient $c) => $c->projects->getStepData($a, $b),
            // Chats
            'chats.create' => fn (QuerriClient $c) => $c->chats->create($a, ['message' => 'm']),
            'chats.list' => fn (QuerriClient $c) => $c->chats->list($a),
            'chats.retrieve' => fn (QuerriClient $c) => $c->chats->retrieve($a, $b),
            'chats.del' => fn (QuerriClient $c) => $c->chats->del($a, $b),
            'chats.cancel' => fn (QuerriClient $c) => $c->chats->cancel($a, $b),
            // Data
            'data.list' => fn (QuerriClient $c) => $c->data->list(),
            'data.retrieve' => fn (QuerriClient $c) => $c->data->retrieve($a),
            'data.create' => fn (QuerriClient $c) => $c->data->create(['name' => 's', 'rows' => [['a' => 1]]]),
            'data.del' => fn (QuerriClient $c) => $c->data->del($a),
            'data.appendRows' => fn (QuerriClient $c) => $c->data->appendRows($a, ['rows' => [['a' => 1]]]),
            'data.replaceData' => fn (QuerriClient $c) => $c->data->replaceData($a, ['rows' => [['a' => 1]]]),
            'data.query' => fn (QuerriClient $c) => $c->data->query($a, ['sql' => 'SELECT 1']),
            'data.getSourceData' => fn (QuerriClient $c) => $c->data->getSourceData($a),
            // Sources & connectors
            'sources.listConnectors' => fn (QuerriClient $c) => $c->sources->listConnectors(),
            'sources.list' => fn (QuerriClient $c) => $c->sources->list(),
            'sources.create' => fn (QuerriClient $c) => $c->sources->create(['name' => 's', 'rows' => [['a' => 1]]]),
            'sources.update' => fn (QuerriClient $c) => $c->sources->update($a, ['name' => 's']),
            'sources.del' => fn (QuerriClient $c) => $c->sources->del($a),
            'sources.sync' => fn (QuerriClient $c) => $c->sources->sync($a),
            // Files
            'files.list' => fn (QuerriClient $c) => $c->files->list(),
            'files.retrieve' => fn (QuerriClient $c) => $c->files->retrieve($a),
            'files.del' => fn (QuerriClient $c) => $c->files->del($a),
            // Keys
            'keys.create' => fn (QuerriClient $c) => $c->keys->create(['name' => 'k']),
            'keys.list' => fn (QuerriClient $c) => $c->keys->list(),
            'keys.retrieve' => fn (QuerriClient $c) => $c->keys->retrieve($a),
            'keys.revoke' => fn (QuerriClient $c) => $c->keys->revoke($a),
            // Audit
            'audit.listEvents' => fn (QuerriClient $c) => $c->audit->listEvents(),
            // Usage
            'usage.getOrgUsage' => fn (QuerriClient $c) => $c->usage->getOrgUsage(),
            'usage.getUserUsage' => fn (QuerriClient $c) => $c->usage->getUserUsage($a),
            // Sharing
            'sharing.shareProject' => fn (QuerriClient $c) => $c->sharing->shareProject($a, ['user_id' => 'u']),
            'sharing.revokeProjectShare' => fn (QuerriClient $c) => $c->sharing->revokeProjectShare($a, $b),
            'sharing.listProjectShares' => fn (QuerriClient $c) => $c->sharing->listProjectShares($a),
            'sharing.shareDashboard' => fn (QuerriClient $c) => $c->sharing->shareDashboard($a, ['user_id' => 'u']),
            'sharing.revokeDashboardShare' => fn (QuerriClient $c) => $c->sharing->revokeDashboardShare($a, $b),
            'sharing.listDashboardShares' => fn (QuerriClient $c) => $c->sharing->listDashboardShares($a),
            'sharing.shareSource' => fn (QuerriClient $c) => $c->sharing->shareSource($a, ['user_id' => 'u']),
            'sharing.orgShareSource' => fn (QuerriClient $c) => $c->sharing->orgShareSource($a, ['enabled' => true]),
        ];

        return array_map(static fn ($fn) => [$fn], $calls);
    }

    /**
     * @param callable(QuerriClient): mixed $invoke
     */
    #[DataProvider('surfaceCases')]
    public function testSdkPathIsInContractSnapshot(callable $invoke): void
    {
        $client = $this->makeQuerriClient([
            new MockResponse(self::BODY, ['http_code' => 200]),
        ]);

        $invoke($client);

        $this->assertNotEmpty($this->recorded, 'call produced no HTTP request');
        $method = strtolower($this->recorded[0]['method']);
        $template = self::templateFromUrl($this->recorded[0]['url']);

        $match = false;
        foreach (self::snapshotPaths() as $path => $methods) {
            if (self::normalizeTemplate((string) $path) === $template
                && in_array($method, array_map('strtolower', $methods), true)
            ) {
                $match = true;
                break;
            }
        }

        $this->assertTrue(
            $match,
            "SDK built {$method} {$template}, which is not in tests/fixtures/openapi.snapshot.json — "
            . 'either the SDK path drifted from the server contract or the snapshot needs updating.',
        );
    }

    public function testSnapshotHasNoUnreachableEntries(): void
    {
        // Reverse direction: every snapshot entry must be producible by some
        // SDK call, so the snapshot can't silently accumulate dead paths.
        $built = [];
        foreach (self::surfaceCases() as [$invoke]) {
            $client = $this->makeQuerriClient([
                new MockResponse(self::BODY, ['http_code' => 200]),
            ]);
            $invoke($client);
            $method = strtolower($this->recorded[0]['method']);
            $built[$method . ' ' . self::templateFromUrl($this->recorded[0]['url'])] = true;
        }

        $stale = [];
        foreach (self::snapshotPaths() as $path => $methods) {
            foreach ($methods as $method) {
                $key = strtolower((string) $method) . ' ' . self::normalizeTemplate((string) $path);
                if (!isset($built[$key])) {
                    $stale[] = "{$method} {$path}";
                }
            }
        }

        $this->assertSame([], $stale, 'snapshot lists paths no SDK method builds');
    }

    /** Extract the request path, strip the /api/v1 prefix, and template the sentinel IDs. */
    private static function templateFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        assert(is_string($path));
        $path = preg_replace('#^/api/v1#', '', $path) ?? $path;

        return str_replace([self::ID_A, self::ID_B], '{}', $path);
    }

    /** Replace named {param} placeholders with the anonymous {} marker. */
    private static function normalizeTemplate(string $template): string
    {
        return preg_replace('/\{[^}]*\}/', '{}', $template) ?? $template;
    }
}
