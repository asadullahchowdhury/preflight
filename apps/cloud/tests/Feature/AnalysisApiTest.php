<?php

namespace Tests\Feature;

use App\GitHub\GitHubApp;
use App\Jobs\PublishAnalysis;
use App\Models\Analysis;
use App\Models\Installation;
use App\Models\Repository;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    private const SCHEMA = "CREATE TABLE `orders` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `status` varchar(50) NOT NULL DEFAULT 'pending', PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    protected function setUp(): void
    {
        parent::setUp();
        config(['preflight.oidc.audience' => 'https://preflight.test', 'preflight.beta' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $statements = []): array
    {
        return [
            'server_version' => '8.0.36',
            'migrations' => ['2026_01_01_000000_widen_status'],
            'migration_files' => ['2026_01_01_000000_widen_status' => '/home/runner/work/app/app/database/migrations/2026_01_01_000000_widen_status.php'],
            'notes' => [],
            'input' => [
                'statements' => $statements ?: [
                    ['sql' => 'set foreign_key_checks = 0', 'migration' => '2026_01_01_000000_widen_status'],
                    ['sql' => "alter table `orders` modify `status` varchar(100) not null default 'pending'", 'migration' => '2026_01_01_000000_widen_status'],
                ],
                'schema_sql' => self::SCHEMA,
                'foreign_key_checks' => true,
                'default_charset' => 'utf8mb4',
            ],
            'context' => [
                'pull_request' => 7,
                'head_sha' => str_repeat('a', 40),
                'workspace' => '/home/runner/work/app/app',
            ],
        ];
    }

    private function repository(): Repository
    {
        return Repository::factory()->create(['github_id' => 4242, 'full_name' => 'acme/app']);
    }

    public function test_it_analyses_and_stores_a_check_from_ci(): void
    {
        Queue::fake();
        $repository = $this->repository();
        $token = $this->oidcToken(['repository_id' => '4242', 'repository' => 'acme/app', 'ref' => 'refs/pull/7/merge', 'sha' => str_repeat('b', 40), 'run_id' => '99', 'actor' => 'octocat', 'event_name' => 'pull_request']);

        $response = $this->withToken($token)->postJson('/api/analyses', $this->payload());

        $response->assertCreated()->assertJson(['severity' => 'danger', 'danger_count' => 1]);
        $analysis = Analysis::query()->sole();
        $this->assertSame($repository->id, $analysis->repository_id);
        $this->assertSame(7, $analysis->pr_number);
        $this->assertSame(str_repeat('a', 40), $analysis->head_sha, 'check runs go on the PR head, not the merge commit');
        $this->assertSame('https://github.com/acme/app/actions/runs/99', $analysis->run_url);
        $this->assertSame('database/migrations/2026_01_01_000000_widen_status.php', $analysis->report['files']['2026_01_01_000000_widen_status']);
        $this->assertSame('COPY', $analysis->findings()->sole()->algorithm);
        $this->assertStringContainsString('<!-- preflight-report -->', $analysis->report['markdown']);
        Queue::assertPushed(PublishAnalysis::class);
    }

    public function test_session_settings_in_the_sql_are_respected(): void
    {
        Queue::fake();
        $this->repository();
        $token = $this->oidcToken(['repository_id' => '4242', 'ref' => 'refs/heads/main']);

        $this->withToken($token)->postJson('/api/analyses', $this->payload([
            ['sql' => 'SET FOREIGN_KEY_CHECKS=0;', 'migration' => 'm'],
            ['sql' => 'alter table `orders` add constraint `fk` foreign key (`id`) references `orders` (`id`)', 'migration' => 'm'],
        ]))->assertCreated();

        // Disabling foreign key checks makes the constraint metadata only.
        $this->assertSame('INPLACE', Analysis::query()->sole()->findings()->sole()->algorithm);
    }

    public function test_it_rejects_requests_without_a_valid_token(): void
    {
        $this->repository();

        $this->postJson('/api/analyses', $this->payload())->assertUnauthorized();

        $this->oidcToken(['repository_id' => '4242']);
        $forged = JWT::encode(['iss' => 'https://token.actions.githubusercontent.com', 'aud' => 'https://preflight.test', 'repository_id' => '4242', 'exp' => time() + 60], $this->otherKey(), 'RS256', 'test-key');
        $this->withToken($forged)->postJson('/api/analyses', $this->payload())->assertUnauthorized();

        $wrongAudience = $this->oidcToken(['repository_id' => '4242', 'aud' => 'https://elsewhere.test']);
        $this->withToken($wrongAudience)->postJson('/api/analyses', $this->payload())
            ->assertUnauthorized()->assertJsonFragment(['message' => 'Invalid OIDC token: The token was issued for a different audience.']);

        $expired = $this->oidcToken(['repository_id' => '4242', 'exp' => time() - 3600]);
        $this->withToken($expired)->postJson('/api/analyses', $this->payload())->assertUnauthorized();

        $this->assertDatabaseCount('analyses', 0);
    }

    public function test_it_refuses_repositories_without_the_app(): void
    {
        $token = $this->oidcToken(['repository_id' => '1', 'repository' => 'someone/else']);

        $this->withToken($token)->postJson('/api/analyses', $this->payload())
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'The Preflight GitHub App is not installed on someone/else.']);
    }

    public function test_it_refuses_suspended_installations(): void
    {
        $repository = $this->repository();
        $repository->installation->update(['suspended_at' => now()]);
        $token = $this->oidcToken(['repository_id' => '4242']);

        $this->withToken($token)->postJson('/api/analyses', $this->payload())->assertForbidden();
    }

    public function test_it_validates_the_payload(): void
    {
        $this->repository();
        $token = $this->oidcToken(['repository_id' => '4242']);

        $this->withToken($token)->postJson('/api/analyses', ['server_version' => '8.0.36'])
            ->assertUnprocessable()->assertJsonValidationErrors(['input', 'migrations']);

        $this->withToken($token)->postJson('/api/analyses', ['server_version' => '5.7.44'] + $this->payload())
            ->assertUnprocessable();
    }

    public function test_publishing_posts_a_check_run_and_a_comment_as_the_app(): void
    {
        config(['preflight.github_app.id' => '123', 'preflight.github_app.private_key' => $this->appPrivateKey()]);
        Queue::fake();
        $installation = Installation::factory()->create(['github_id' => 555]);
        Repository::factory()->for($installation)->create(['github_id' => 4242, 'full_name' => 'acme/app']);
        $token = $this->oidcToken(['repository_id' => '4242', 'ref' => 'refs/pull/7/merge', 'event_name' => 'pull_request']);
        $this->withToken($token)->postJson('/api/analyses', $this->payload())->assertCreated();
        $analysis = Analysis::query()->sole();

        Http::fake([
            'api.github.com/app/installations/555/access_tokens' => Http::response(['token' => 'ghs_installation']),
            'api.github.com/repos/acme/app/check-runs' => Http::response(['id' => 9001], 201),
            'api.github.com/repos/acme/app/issues/7/comments*' => Http::sequence()
                ->push([['id' => 1, 'body' => 'unrelated']])
                ->push(['id' => 7001], 201),
        ]);

        (new PublishAnalysis($analysis))->handle(app(GitHubApp::class));

        $analysis->refresh();
        $this->assertSame(Analysis::COMPLETED, $analysis->status);
        $this->assertSame(9001, $analysis->check_run_id);
        $this->assertSame(7001, $analysis->comment_id);

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/check-runs')) {
                return false;
            }

            return $request['conclusion'] === 'failure'
                && $request['head_sha'] === str_repeat('a', 40)
                && $request->hasHeader('Authorization', 'Bearer ghs_installation')
                && $request['output']['annotations'][0]['path'] === 'database/migrations/2026_01_01_000000_widen_status.php'
                && $request['output']['annotations'][0]['annotation_level'] === 'failure';
        });
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/issues/7/comments')
            && str_starts_with($request['body'], '<!-- preflight-report -->'));
    }

    public function test_publishing_updates_the_existing_comment(): void
    {
        config(['preflight.github_app.id' => '123', 'preflight.github_app.private_key' => $this->appPrivateKey()]);
        Queue::fake();
        $installation = Installation::factory()->create(['github_id' => 556]);
        Repository::factory()->for($installation)->create(['github_id' => 4242, 'full_name' => 'acme/app']);
        $token = $this->oidcToken(['repository_id' => '4242', 'ref' => 'refs/pull/7/merge']);
        $this->withToken($token)->postJson('/api/analyses', $this->payload())->assertCreated();

        Http::fake([
            'api.github.com/app/installations/556/access_tokens' => Http::response(['token' => 'ghs_x']),
            'api.github.com/repos/acme/app/check-runs' => Http::response(['id' => 1], 201),
            'api.github.com/repos/acme/app/issues/7/comments*' => Http::response([['id' => 31, 'body' => "<!-- preflight-report -->\nold"]]),
            'api.github.com/repos/acme/app/issues/comments/31' => Http::response(['id' => 31]),
        ]);

        (new PublishAnalysis(Analysis::query()->sole()))->handle(app(GitHubApp::class));

        $this->assertSame(31, Analysis::query()->sole()->comment_id);
        Http::assertSent(fn ($request) => $request->method() === 'PATCH' && str_ends_with($request->url(), '/issues/comments/31'));
        Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/issues/7/comments'));
    }

    private function otherKey(): string
    {
        return (string) file_get_contents(dirname(__DIR__).'/Fixtures/other-key.pem');
    }
}
