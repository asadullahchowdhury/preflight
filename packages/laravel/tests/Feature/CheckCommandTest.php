<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Preflight\Laravel\Tests\TestCase;
use Symfony\Component\Process\Process;

/**
 * @return array<string, mixed>
 */
function runCheck(array $options, ?int &$exitCode = null): array
{
    $exitCode = Artisan::call('preflight:check', $options + ['--format' => 'json']);
    $json = json_decode(Artisan::output(), true);
    expect($json)->toBeArray('Not JSON: '.Artisan::output());

    return $json;
}

function migrateBase(): void
{
    Artisan::call('migrate', ['--path' => TestCase::fixturesPath('base'), '--realpath' => true]);
}

function tempFile(string $extension): string
{
    $path = sys_get_temp_dir().'/preflight-'.bin2hex(random_bytes(6)).'.'.$extension;
    test()->beforeApplicationDestroyed(static fn () => @unlink($path));

    return $path;
}

/**
 * A throwaway git repository: base migrations committed on main, then a feature branch that adds two
 * migrations, edits one base migration and leaves a third new migration untracked.
 */
function makeGitRepo(): string
{
    $repo = sys_get_temp_dir().'/preflight-repo-'.bin2hex(random_bytes(6));
    $migrations = $repo.'/database/migrations';
    mkdir($migrations, 0777, true);
    test()->beforeApplicationDestroyed(static fn () => (new Filesystem)->deleteDirectory($repo));

    $git = static function (string ...$args) use ($repo): void {
        $process = new Process(['git', '-c', 'user.name=Preflight', '-c', 'user.email=preflight@example.test', '-c', 'commit.gpgsign=false', ...$args], $repo);
        $process->mustRun();
    };

    $git('init', '--quiet', '--initial-branch=main');
    foreach (glob(TestCase::fixturesPath('base').'/*.php') ?: [] as $file) {
        copy($file, $migrations.'/'.basename($file));
    }
    $git('add', '.');
    $git('commit', '--quiet', '-m', 'base');

    $git('checkout', '--quiet', '-b', 'feature');
    $new = glob(TestCase::fixturesPath('new').'/*.php') ?: [];
    sort($new);
    foreach (array_slice($new, 0, 2) as $file) {
        copy($file, $migrations.'/'.basename($file));
    }
    file_put_contents($migrations.'/2024_01_02_000000_create_orders_table.php', "\n// edited on the branch\n", FILE_APPEND);
    $git('add', '.');
    $git('commit', '--quiet', '-m', 'feature');
    copy($new[2], $migrations.'/'.basename($new[2]));

    return $repo;
}

/**
 * @param  array<string, mixed>  $json
 * @return list<array{migration: ?string, severity: string, algorithm: ?string, rules: list<string>}>
 */
function statementSummary(array $json): array
{
    return array_map(static fn (array $s) => [
        'migration' => $s['migration'],
        'severity' => $s['severity'],
        'algorithm' => $s['algorithm'],
        'rules' => array_map(static fn (array $f) => $f['rule'], $s['findings']),
    ], $json['statements']);
}

it('checks pending migrations against the current schema without running them', function () {
    migrateBase();

    $json = runCheck([
        '--path' => [TestCase::fixturesPath('base'), TestCase::fixturesPath('new')],
        '--realpath' => true,
        '--mysql' => '8.0.36',
    ], $exitCode);

    expect($exitCode)->toBe(1)
        ->and($json['severity'])->toBe('danger')
        ->and($json['server_version'])->toBe('8.0.36')
        ->and($json['migrations'])->toBe([
            '2026_01_01_000000_widen_orders_status',
            '2026_01_02_000000_add_orders_indexes',
            '2026_01_03_000000_create_refunds_table',
        ])
        ->and($json['base_migrations'])->toBe(0)
        ->and(statementSummary($json))->toBe([
            ['migration' => '2026_01_01_000000_widen_orders_status', 'severity' => 'danger', 'algorithm' => 'COPY', 'rules' => ['modify-column']],
            ['migration' => '2026_01_02_000000_add_orders_indexes', 'severity' => 'warn', 'algorithm' => 'INPLACE', 'rules' => ['add-index']],
            ['migration' => '2026_01_02_000000_add_orders_indexes', 'severity' => 'danger', 'algorithm' => 'COPY', 'rules' => ['add-foreign-key']],
            ['migration' => '2026_01_02_000000_add_orders_indexes', 'severity' => 'info', 'algorithm' => 'INSTANT', 'rules' => ['add-column']],
            ['migration' => '2026_01_03_000000_create_refunds_table', 'severity' => 'info', 'algorithm' => null, 'rules' => ['create-table']],
            ['migration' => '2026_01_03_000000_create_refunds_table', 'severity' => 'info', 'algorithm' => 'COPY', 'rules' => ['add-foreign-key']],
            ['migration' => '2026_01_03_000000_create_refunds_table', 'severity' => 'info', 'algorithm' => null, 'rules' => ['data-change']],
        ]);

    // Nothing ran: the column is unchanged and the new table does not exist.
    expect(Schema::hasTable('refunds'))->toBeFalse()
        ->and(DB::table('migrations')->count())->toBe(2)
        ->and(Schema::getColumnType('orders', 'status', fullDefinition: true))->toBe('varchar(50)');
});

it('runs the base migrations and checks only what the branch adds (--base)', function () {
    $repo = makeGitRepo();

    $json = runCheck(['--base' => 'main', '--path' => [$repo.'/database/migrations'], '--realpath' => true, '--mysql' => '8.0.36']);

    expect($json['migrations'])->toBe([
        '2026_01_01_000000_widen_orders_status',   // committed on the branch
        '2026_01_02_000000_add_orders_indexes',    // committed on the branch
        '2026_01_03_000000_create_refunds_table',  // untracked
    ])
        ->and($json['base_migrations'])->toBe(2)
        ->and($json['notes'])->toHaveCount(1)
        ->and($json['notes'][0])->toContain('2024_01_02_000000_create_orders_table already exists on the base branch and was edited')
        ->and($json['severity'])->toBe('danger');

    // The base schema was built; the migrations under review were not run.
    expect(DB::table('migrations')->pluck('migration')->all())->toBe(['2024_01_01_000000_create_users_table', '2024_01_02_000000_create_orders_table'])
        ->and(Schema::hasTable('refunds'))->toBeFalse();
});

it('fails clearly when the base ref does not exist', function () {
    $repo = makeGitRepo();

    $exitCode = Artisan::call('preflight:check', ['--base' => 'origin/nope', '--path' => [$repo.'/database/migrations'], '--realpath' => true]);

    expect($exitCode)->toBe(2)
        ->and(Artisan::output())->toContain('Git ref [origin/nope] was not found')
        ->and(Schema::hasTable('orders'))->toBeFalse();
});

it('refuses to run base migrations in production without --force', function () {
    $repo = makeGitRepo();
    app()->detectEnvironment(fn () => 'production');

    $exitCode = Artisan::call('preflight:check', ['--base' => 'main', '--path' => [$repo.'/database/migrations'], '--realpath' => true]);

    expect($exitCode)->toBe(2)
        ->and(Artisan::output())->toContain('the application is in production')
        ->and(Schema::hasTable('orders'))->toBeFalse();
});

it('checks only the migrations named with --migration', function () {
    migrateBase();

    $json = runCheck([
        '--path' => [TestCase::fixturesPath('base'), TestCase::fixturesPath('new')],
        '--realpath' => true,
        '--migration' => ['2026_01_03_000000_create_refunds_table.php'],
    ], $exitCode);

    expect($json['migrations'])->toBe(['2026_01_03_000000_create_refunds_table'])
        ->and($json['severity'])->toBe('info')
        ->and($exitCode)->toBe(0);
});

it('notes migrations that already ran instead of analysing them', function () {
    migrateBase();

    $json = runCheck([
        '--path' => [TestCase::fixturesPath('base')],
        '--realpath' => true,
        '--migration' => ['2024_01_02_000000_create_orders_table'],
    ]);

    expect($json['migrations'])->toBe([])
        ->and($json['statements'])->toBe([])
        ->and($json['notes'][0])->toContain('2024_01_02_000000_create_orders_table has already run on this database');
});

it('uses the MySQL version production runs', function () {
    migrateBase();
    $options = ['--path' => [TestCase::fixturesPath('base'), TestCase::fixturesPath('new')], '--realpath' => true, '--migration' => ['2026_01_02_000000_add_orders_indexes']];

    // ADD COLUMN ... AFTER is INSTANT from 8.0.29, an INPLACE rebuild before.
    $old = runCheck($options + ['--mysql' => '8.0.28']);
    $new = runCheck($options + ['--mysql' => '8.0.36']);
    $connected = runCheck($options);

    expect($old['statements'][2]['algorithm'])->toBe('INPLACE')
        ->and($old['statements'][2]['rebuilds_table'])->toBeTrue()
        ->and($new['statements'][2]['algorithm'])->toBe('INSTANT')
        ->and($connected['server_version'])->toBe(preg_replace('/^(\d+\.\d+\.\d+).*$/', '$1', $connected['connected_server_version']));
});

it('estimates durations from a stats file and takes the production version from it', function () {
    migrateBase();
    $stats = tempFile('json');
    file_put_contents($stats, json_encode([
        'preflight_stats' => 1,
        'server_version' => '8.0.28',
        'tables' => ['orders' => ['rows' => 40_000_000, 'data_bytes' => 20 * 1024 ** 3, 'index_bytes' => 5 * 1024 ** 3]],
    ]));

    $json = runCheck(['--path' => [TestCase::fixturesPath('base'), TestCase::fixturesPath('new')], '--realpath' => true, '--stats' => $stats]);

    expect($json['size_aware'])->toBeTrue()
        ->and($json['server_version'])->toBe('8.0.28')
        ->and($json['statements'][0]['estimate']['label'])->toBe('9 min – 43 min')
        ->and($json['statements'][0]['severity'])->toBe('danger');
});

it('prints text, writes markdown and json files, and honours --fail-on', function () {
    migrateBase();
    $markdown = tempFile('md');
    $jsonFile = tempFile('json');
    $paths = ['--path' => [TestCase::fixturesPath('base'), TestCase::fixturesPath('new')], '--realpath' => true];

    $exitCode = Artisan::call('preflight:check', $paths + ['--markdown' => $markdown, '--json' => $jsonFile, '--fail-on' => 'never']);
    $text = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($text)->toContain("2026_01_01_000000_widen_orders_status\nDANGER alter table `orders` modify `status` varchar(100)")
        ->and($text)->toContain('Overall: DANGER')
        ->and(file_get_contents($markdown))->toContain('### 🔴 Preflight: 2 risky statements, 1 warning')
        ->and(file_get_contents($markdown))->toContain('<sub>Checked 3 migrations: `2026_01_01_000000_widen_orders_status`')
        ->and(json_decode((string) file_get_contents($jsonFile), true)['severity'])->toBe('danger');

    expect(Artisan::call('preflight:check', $paths + ['--migration' => ['2026_01_02_000000_add_orders_indexes'], '--fail-on' => 'warn']))->toBe(1)
        ->and(Artisan::call('preflight:check', $paths + ['--migration' => ['2026_01_03_000000_create_refunds_table'], '--fail-on' => 'warn']))->toBe(0);
});

it('says so when there is nothing to check', function () {
    migrateBase();

    expect(Artisan::call('preflight:check', ['--path' => [TestCase::fixturesPath('base')], '--realpath' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('No new migrations to check.');
});

it('rejects connections that are not MySQL', function () {
    expect(Artisan::call('preflight:check', ['--database' => 'sqlite']))->toBe(2)
        ->and(Artisan::output())->toContain('uses the [sqlite] driver');
});

it('validates options', function () {
    expect(Artisan::call('preflight:check', ['--format' => 'xml']))->toBe(2)
        ->and(Artisan::output())->toContain('--format must be one of: text, markdown, json');
});
