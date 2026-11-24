<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
