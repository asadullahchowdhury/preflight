<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Preflight\Laravel\Stats\StatsFile;
use Preflight\Laravel\Tests\TestCase;

it('captures table sizes as JSON', function () {
    Artisan::call('migrate', ['--path' => TestCase::fixturesPath('base'), '--realpath' => true]);
    DB::table('users')->insert(['name' => 'Ada', 'email' => 'ada@example.test']);

    expect(Artisan::call('preflight:stats'))->toBe(0);
    $json = json_decode(Artisan::output(), true);

    expect($json['preflight_stats'])->toBe(1)
        ->and($json['server_version'])->toBe(DB::selectOne('select version() as v')->v)
        ->and($json['captured_at'])->toBeString()
        ->and(array_keys($json['tables']))->toBe(['migrations', 'orders', 'users'])
        ->and(array_keys($json['tables']['users']))->toBe(['rows', 'data_bytes', 'index_bytes'])
        ->and($json['tables']['users']['data_bytes'])->toBeGreaterThan(0);
});

it('writes a file that preflight:check reads back', function () {
    Artisan::call('migrate', ['--path' => TestCase::fixturesPath('base'), '--realpath' => true]);
    $path = sys_get_temp_dir().'/preflight-stats-'.bin2hex(random_bytes(6)).'.json';

    expect(Artisan::call('preflight:stats', ['--output' => $path]))->toBe(0)
        ->and(Artisan::output())->toContain('Wrote sizes of 3 tables');

    $stats = StatsFile::read($path);
    @unlink($path);

    expect($stats->tables)->toHaveKeys(['orders', 'users'])
        ->and($stats->toStatsSet()->for('ORDERS'))->not->toBeNull();
});

it('accepts the plain core stats format', function () {
    $path = sys_get_temp_dir().'/preflight-stats-'.bin2hex(random_bytes(6)).'.json';
    file_put_contents($path, '{"orders": {"rows": 10, "data_bytes": 16384, "index_bytes": 0}}');

    $stats = StatsFile::read($path);
    @unlink($path);

    expect($stats->serverVersion)->toBeNull()
        ->and($stats->tables['orders'])->toBe(['rows' => 10, 'data_bytes' => 16384, 'index_bytes' => 0]);
});

it('pushes sizes to Preflight Cloud with the stats token', function () {
    Artisan::call('migrate', ['--path' => TestCase::fixturesPath('base'), '--realpath' => true]);
    config(['preflight.cloud_url' => 'https://preflight.test/', 'preflight.stats_token' => 'pfs_secret']);
    Http::fake(['preflight.test/api/stats' => Http::response(['message' => 'ok', 'tables' => 3], 201)]);

    expect(Artisan::call('preflight:stats', ['--push' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('Sent sizes of 3 tables to Preflight Cloud.');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://preflight.test/api/stats'
        && $request->hasHeader('Authorization', 'Bearer pfs_secret')
        && $request['preflight_stats'] === 1
        && array_keys($request['tables']) === ['migrations', 'orders', 'users']
        && array_keys($request['tables']['orders']) === ['rows', 'data_bytes', 'index_bytes']);
});

it('reports a rejected push', function () {
    config(['preflight.cloud_url' => 'https://preflight.test', 'preflight.stats_token' => 'pfs_wrong']);
    Http::fake(['preflight.test/*' => Http::response(['message' => 'Invalid stats token.'], 401)]);

    expect(Artisan::call('preflight:stats', ['--push' => true]))->toBe(1)
        ->and(Artisan::output())->toContain('Preflight Cloud answered 401: Invalid stats token.');
});

it('explains what is missing before pushing', function () {
    config(['preflight.cloud_url' => null, 'preflight.stats_token' => null]);

    expect(Artisan::call('preflight:stats', ['--push' => true]))->toBe(2)
        ->and(Artisan::output())->toContain('Set PREFLIGHT_CLOUD_URL and PREFLIGHT_STATS_TOKEN');
});
