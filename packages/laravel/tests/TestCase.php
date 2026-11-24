<?php

declare(strict_types=1);

namespace Preflight\Laravel\Tests;

use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use PDO;
use Preflight\Laravel\PreflightServiceProvider;

/**
 * Feature tests run against a real MySQL server. Every table in the test database is dropped between tests,
 * so its name must contain "preflight".
 *
 *   PREFLIGHT_TEST_DB_HOST=127.0.0.1 PREFLIGHT_TEST_DB_PORT=3306 PREFLIGHT_TEST_DB_USER=root
 *   PREFLIGHT_TEST_DB_PASSWORD= PREFLIGHT_TEST_DB_DATABASE=preflight_laravel_test
 */
abstract class TestCase extends Orchestra
{
    private static bool $databaseReady = false;

    protected function getPackageProviders($app): array
    {
        return [PreflightServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => self::env('HOST', '127.0.0.1'),
            'port' => self::env('PORT', '3306'),
            'database' => self::database(),
            'username' => self::env('USER', 'root'),
            'password' => self::env('PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);
        $app['config']->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }

    protected function setUp(): void
    {
        self::createDatabase();
        parent::setUp();
        $this->dropAllTables();
    }

    protected function dropAllTables(): void
    {
        $connection = DB::connection('mysql');
        $connection->statement('set foreign_key_checks = 0');
        foreach ($connection->select("show full tables where Table_type = 'BASE TABLE'") as $row) {
            $table = (string) array_values((array) $row)[0];
            $connection->statement('drop table `'.str_replace('`', '``', $table).'`');
        }
        $connection->statement('set foreign_key_checks = 1');
    }

    public static function fixturesPath(string $path = ''): string
    {
        return str_replace('\\', '/', __DIR__.'/Fixtures/migrations'.($path !== '' ? '/'.$path : ''));
    }

    private static function createDatabase(): void
    {
        if (self::$databaseReady) {
            return;
        }

        $database = self::database();
        if (! str_contains($database, 'preflight')) {
            throw new \RuntimeException('PREFLIGHT_TEST_DB_DATABASE must contain "preflight": every table in it is dropped.');
        }

        $pdo = new PDO('mysql:host='.self::env('HOST', '127.0.0.1').';port='.self::env('PORT', '3306'), self::env('USER', 'root'), self::env('PASSWORD', ''));
        $pdo->exec('create database if not exists `'.$database.'` character set utf8mb4 collate utf8mb4_unicode_ci');
        self::$databaseReady = true;
    }

    private static function database(): string
    {
        return self::env('DATABASE', 'preflight_laravel_test');
    }

    private static function env(string $key, string $default): string
    {
        $value = getenv('PREFLIGHT_TEST_DB_'.$key);

        return $value === false ? $default : $value;
    }
}
