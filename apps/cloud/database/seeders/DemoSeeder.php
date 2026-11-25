<?php

namespace Database\Seeders;

use App\Analysis\AnalysisRunner;
use App\Models\Analysis;
use App\Models\Installation;
use App\Models\Repository;
use App\Models\TableStat;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data for working on the dashboard: one user, two installations, a few repositories and analyses
 * produced by the real engine.
 */
class DemoSeeder extends Seeder
{
    public const LOGIN = 'demo';

    private const SCHEMA = <<<'SQL'
        CREATE TABLE `users` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `email` varchar(255) NOT NULL,
          `bio` text,
          PRIMARY KEY (`id`),
          UNIQUE KEY `users_email_unique` (`email`),
          FULLTEXT KEY `users_bio_fulltext` (`bio`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        CREATE TABLE `orders` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `user_id` bigint unsigned NOT NULL,
          `customer_email` varchar(255) NOT NULL,
          `status` varchar(50) NOT NULL DEFAULT 'pending',
          `total` decimal(10,2) NOT NULL,
          `created_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        CREATE TABLE `jobs` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `queue` varchar(255) NOT NULL,
          `payload` longtext NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

    /** @var array<string, list<array{sql: string, migration: string}>> */
    private const MIGRATIONS = [
        'widen-status' => [
            ['sql' => "alter table `orders` modify `status` varchar(100) not null default 'pending'", 'migration' => '2026_11_02_101500_widen_order_status'],
            ['sql' => 'alter table `orders` add index `orders_customer_email_index`(`customer_email`)', 'migration' => '2026_11_02_101500_widen_order_status'],
        ],
        'foreign-key' => [
            ['sql' => 'alter table `orders` add constraint `orders_user_id_foreign` foreign key (`user_id`) references `users` (`id`)', 'migration' => '2026_11_05_090000_add_order_user_foreign'],
        ],
        'safe' => [
            ['sql' => 'alter table `orders` add `notes` text null after `status`', 'migration' => '2026_11_09_120000_add_notes_to_orders'],
            ['sql' => "alter table `orders` alter `status` set default 'new'", 'migration' => '2026_11_09_120000_add_notes_to_orders'],
        ],
        'rename' => [
            ['sql' => 'alter table `jobs` rename column `payload` to `body`', 'migration' => '2026_11_12_080000_rename_jobs_payload'],
        ],
        'fulltext' => [
            ['sql' => 'create table `teams` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null)', 'migration' => '2026_11_18_140000_create_teams_table'],
            ['sql' => 'alter table `users` add `team_id` bigint unsigned null', 'migration' => '2026_11_18_140000_create_teams_table'],
            ['sql' => 'alter table `users` add constraint `users_team_id_foreign` foreign key (`team_id`) references `teams` (`id`)', 'migration' => '2026_11_18_140000_create_teams_table'],
        ],
        'empty' => [],
    ];

    /**
     * Today's production sizes per repository: [rows, data GB, index GB, daily growth].
     *
     * @var array<string, array<string, array{0: int, 1: float, 2: float, 3: float}>>
     */
    private const PRODUCTION_SIZES = [
        'acme/storefront' => [
            'orders' => [41_200_000, 18.4, 6.1, 0.004],
            'users' => [2_300_000, 1.9, 0.6, 0.002],
            'jobs' => [1_200, 0.002, 0.0005, 0.0],
            'order_items' => [118_000_000, 22.0, 9.5, 0.005],
            'settings' => [40, 0.00002, 0.00001, 0.0],
        ],
        'acme/billing-api' => [
            'users' => [860_000, 0.7, 0.3, 0.001],
            'jobs' => [300, 0.001, 0.0005, 0.0],
            'invoices' => [9_400_000, 6.2, 2.1, 0.003],
        ],
    ];

    /**
     * Thirty days of history ending today, growing towards today's sizes.
     *
     * @param  array<string, array{0: int, 1: float, 2: float, 3: float}>  $tables
     */
    private function seedSizes(Repository $repository, array $tables): void
    {
        $gb = 1024 ** 3;
        for ($day = 29; $day >= 0; $day--) {
            foreach ($tables as $name => [$rows, $data, $index, $growth]) {
                $factor = (1 - $growth) ** $day;
                TableStat::query()->updateOrCreate(
                    ['repository_id' => $repository->id, 'table_name' => $name, 'captured_on' => now()->subDays($day)->toDateString()],
                    ['rows' => (int) ($rows * $factor), 'data_bytes' => (int) ($data * $gb * $factor), 'index_bytes' => (int) ($index * $gb * $factor)],
                );
            }
        }

        $repository->forceFill([
            'stats_pushed_at' => now()->subHours(3),
            'stats_captured_on' => now()->toDateString(),
            'stats_server_version' => '8.0.40',
            'stats_token_hash' => hash('sha256', 'pfs_demo_'.$repository->id),
            'stats_token_created_at' => now()->subDays(30),
        ])->save();
    }

    public function run(): void
    {
        $user = User::query()->updateOrCreate(['github_login' => self::LOGIN], [
            'github_id' => 1,
            'name' => 'Demo Developer',
            'email' => 'demo@example.test',
            'avatar_url' => null,
            'github_token' => null,
        ]);
        $user->forceFill(['approved_at' => $user->approved_at ?? now()])->save();

        // People waiting for the private beta, for the admin page (the demo user is an admin locally
        // when PREFLIGHT_ADMINS includes "demo").
        $waiting = [
            ['priya-dev', 'Priya Sharma', '11-50', 'Our 60M-row events table: every ->change() is a gamble.', 2],
            ['tomasz', 'Tomasz Nowak', '2-10', 'Adding foreign keys to old tables without downtime.', 5],
            ['lin-ops', 'Lin Chen', '51-200', null, 9],
        ];
        foreach ($waiting as $i => [$login, $name, $size, $note, $daysAgo]) {
            $person = User::query()->updateOrCreate(['github_login' => $login], [
                'github_id' => 900 + $i, 'name' => $name, 'team_size' => $size, 'waitlist_note' => $note,
            ]);
            $person->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
        }

        $acme = Installation::query()->updateOrCreate(['github_id' => 1001], [
            'account_id' => 2001, 'account_login' => 'acme', 'account_type' => 'Organization', 'repository_selection' => 'selected',
        ]);
        $personal = Installation::query()->updateOrCreate(['github_id' => 1002], [
            'account_id' => 1, 'account_login' => self::LOGIN, 'account_type' => 'User', 'repository_selection' => 'all',
        ]);
        $user->installations()->syncWithoutDetaching([$acme->id, $personal->id]);

        $repositories = [
            'acme/storefront' => [$acme, ['widen-status' => [412, 3], 'foreign-key' => [415, 2], 'safe' => [418, 1]]],
            'acme/billing-api' => [$acme, ['fulltext' => [88, 0], 'rename' => [86, 5]]],
            'acme/warehouse' => [$acme, ['safe' => [57, 8]]],
            'demo/side-project' => [$personal, ['empty' => [12, 6]]],
            'demo/blog' => [$personal, []],
        ];

        $runner = new AnalysisRunner;
        $githubId = 50_000;
        foreach ($repositories as $fullName => [$installation, $runs]) {
            $repository = Repository::query()->updateOrCreate(['full_name' => $fullName], [
                'installation_id' => $installation->id, 'github_id' => $githubId++, 'private' => ! str_ends_with($fullName, 'blog'),
            ]);
            if ($repository->analyses()->exists()) {
                continue;
            }

            if (isset(self::PRODUCTION_SIZES[$fullName])) {
                $this->seedSizes($repository, self::PRODUCTION_SIZES[$fullName]);
            }

            foreach ($runs as $scenario => [$pr, $daysAgo]) {
                $statements = self::MIGRATIONS[$scenario];
                $analysis = $runner->run($repository, [
                    'server_version' => '8.0.36',
                    'migrations' => array_values(array_unique(array_column($statements, 'migration'))),
                    'input' => ['statements' => $statements, 'schema_sql' => self::SCHEMA, 'foreign_key_checks' => true, 'default_charset' => 'utf8mb4'],
                    'context' => ['head_sha' => sha1($fullName.$pr)],
                ], [
                    'ref' => "refs/pull/{$pr}/merge",
                    'sha' => sha1($fullName.$pr.'merge'),
                    'repository' => $fullName,
                    'run_id' => 9_000_000 + $pr,
                    'actor' => self::LOGIN,
                    'base_ref' => 'main',
                ]);

                $at = now()->subDays($daysAgo)->subMinutes($pr % 50);
                $analysis->forceFill(['status' => Analysis::COMPLETED, 'created_at' => $at, 'updated_at' => $at])->save();
            }
        }
    }
}
