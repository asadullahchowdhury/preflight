<?php

namespace Tests\Feature;

use App\Analysis\AnalysisRunner;
use App\Models\Repository;
use App\Models\TableStat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    private const GB = 1024 ** 3;

    /**
     * @param  array<string, array{rows: int, data_bytes: int, index_bytes: int}>  $tables
     * @return array<string, mixed>
     */
    private function stats(array $tables, string $capturedAt = '2026-11-24T03:00:00+00:00', string $version = '8.0.40'): array
    {
        return ['preflight_stats' => 1, 'server_version' => $version, 'captured_at' => $capturedAt, 'tables' => $tables];
    }

    public function test_pushing_requires_a_valid_token(): void
    {
        $this->postJson('/api/stats', $this->stats([]))->assertUnauthorized();
        $this->withToken('pfs_not-a-real-token')->postJson('/api/stats', $this->stats([]))->assertUnauthorized();

        $repository = Repository::factory()->create();
        $token = $repository->issueStatsToken();
        $repository->issueStatsToken(); // a new token replaces the old one

        $this->withToken($token)->postJson('/api/stats', $this->stats([]))->assertUnauthorized();
    }

    public function test_pushes_store_daily_snapshots(): void
    {
        $repository = Repository::factory()->create();
        $token = $repository->issueStatsToken();
        $this->assertSame(64, strlen((string) $repository->fresh()->stats_token_hash), 'only the hash is stored');

        $this->withToken($token)->postJson('/api/stats', $this->stats([
            'orders' => ['rows' => 100, 'data_bytes' => 16_384, 'index_bytes' => 0],
        ], '2026-11-23T03:00:00+00:00'))->assertCreated();

        $this->withToken($token)->postJson('/api/stats', $this->stats([
            'orders' => ['rows' => 41_000_000, 'data_bytes' => 18 * self::GB, 'index_bytes' => 6 * self::GB],
            'users' => ['rows' => 5_000, 'data_bytes' => 1_048_576, 'index_bytes' => 0],
        ]))->assertCreated()->assertJson(['tables' => 2]);

        // Same day again: replaces that day's snapshot.
        $this->withToken($token)->postJson('/api/stats', $this->stats([
            'orders' => ['rows' => 41_500_000, 'data_bytes' => 18 * self::GB, 'index_bytes' => 6 * self::GB],
            'users' => ['rows' => 5_000, 'data_bytes' => 1_048_576, 'index_bytes' => 0],
        ]))->assertCreated();

        $repository->refresh();
        $this->assertSame('2026-11-24', $repository->stats_captured_on?->toDateString());
        $this->assertSame('8.0.40', $repository->stats_server_version);
        $this->assertSame(3, TableStat::query()->count(), 'two days of orders, one of users');
        $this->assertSame(41_500_000, $repository->latestStats()?->for('orders')?->rows);
    }

    public function test_pushes_are_validated(): void
    {
        $repository = Repository::factory()->create();
        $token = $repository->issueStatsToken();

        $this->withToken($token)->postJson('/api/stats', ['tables' => ['orders' => ['rows' => -1]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preflight_stats', 'tables.orders.rows', 'tables.orders.data_bytes']);
    }

    public function test_analyses_use_the_latest_production_sizes(): void
    {
        Carbon::setTestNow('2026-11-24 12:00:00');
        $repository = Repository::factory()->create();
        $token = $repository->issueStatsToken();
        $this->withToken($token)->postJson('/api/stats', $this->stats([
            'orders' => ['rows' => 41_000_000, 'data_bytes' => 18 * self::GB, 'index_bytes' => 6 * self::GB],
            'settings' => ['rows' => 12, 'data_bytes' => 16_384, 'index_bytes' => 0],
        ], version: '8.0.40'))->assertCreated();

        $schema = "CREATE TABLE `orders` (`id` bigint NOT NULL, `status` varchar(50) NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARSET=utf8mb4;\n"
            .'CREATE TABLE `settings` (`id` bigint NOT NULL, `value` varchar(50) NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARSET=utf8mb4;';

        $analysis = (new AnalysisRunner)->run($repository->fresh(), [
            'server_version' => '8.0.36',
            'migrations' => ['m'],
            'input' => ['statements' => [
                ['sql' => 'alter table `orders` modify `status` varchar(100) not null', 'migration' => 'm'],
                ['sql' => 'alter table `settings` modify `value` varchar(100) not null', 'migration' => 'm'],
            ], 'schema_sql' => $schema],
        ], ['ref' => 'refs/pull/3/merge']);

        $this->assertTrue($analysis->size_aware);
        $this->assertSame('8.0.40', $analysis->mysql_version, 'production reported its version with the sizes');
        [$orders, $settings] = $analysis->report['statements'];

        // The same table copy: dangerous on 24 GB, harmless on a 12-row table.
        $this->assertSame('danger', $orders['severity']);
        $this->assertMatchesRegularExpression('/min/', $orders['estimate']['label']);
        $this->assertSame('info', $settings['severity']);
        $this->assertSame('<1s', $settings['estimate']['label']);
        $this->assertSame(1, $analysis->danger_count);
    }

    public function test_old_sizes_are_flagged(): void
    {
        Carbon::setTestNow('2026-11-24 12:00:00');
        $repository = Repository::factory()->create();
        $this->withToken($repository->issueStatsToken())->postJson('/api/stats', $this->stats([
            'orders' => ['rows' => 10, 'data_bytes' => 16_384, 'index_bytes' => 0],
        ], '2026-10-01T03:00:00+00:00'))->assertCreated();

        $analysis = (new AnalysisRunner)->run($repository->fresh(), [
            'server_version' => '8.0.36',
            'migrations' => [],
            'input' => ['statements' => [], 'schema_sql' => ''],
        ], []);

        $this->assertStringContainsString('Production table sizes are from Oct 1, 2026', $analysis->report['notes'][0]);
    }

    public function test_tokens_are_managed_from_the_repository_page(): void
    {
        $user = User::factory()->create();
        $repository = Repository::factory()->create();
        $user->installations()->attach($repository->installation_id);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post(route('repositories.stats-token.store', $repository))->assertNotFound();

        $response = $this->actingAs($user)->from(route('repositories.show', $repository))
            ->post(route('repositories.stats-token.store', $repository));
        $response->assertRedirect(route('repositories.show', $repository));
        $token = session('statsToken');
        $this->assertStringStartsWith('pfs_', $token);
        $this->assertTrue(Repository::forStatsToken($token)?->is($repository));

        $this->actingAs($user)->delete(route('repositories.stats-token.destroy', $repository))->assertRedirect();
        $this->assertNull(Repository::forStatsToken($token));
    }

    public function test_the_repository_page_shows_tables_biggest_first_with_history(): void
    {
        $user = User::factory()->create();
        $repository = Repository::factory()->create();
        $user->installations()->attach($repository->installation_id);
        $token = $repository->issueStatsToken();
        foreach (['2026-11-22', '2026-11-23', '2026-11-24'] as $i => $day) {
            $this->withToken($token)->postJson('/api/stats', $this->stats([
                'orders' => ['rows' => 1000 * ($i + 1), 'data_bytes' => self::GB * ($i + 1), 'index_bytes' => 0],
                'users' => ['rows' => 10, 'data_bytes' => 16_384, 'index_bytes' => 16_384],
            ], $day.'T03:00:00+00:00'))->assertCreated();
        }

        $this->actingAs($user)->get(route('repositories.show', $repository))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.hasToken', true)
                ->where('stats.capturedOn', '2026-11-24')
                ->where('stats.tableCount', 2)
                ->where('stats.tables.0.name', 'orders')
                ->where('stats.tables.0.rows', 3000)
                ->has('stats.tables.0.history', 3));
    }
}
