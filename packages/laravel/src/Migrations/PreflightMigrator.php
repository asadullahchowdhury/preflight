<?php

declare(strict_types=1);

namespace Preflight\Laravel\Migrations;

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Migrations\Migrator;
use Throwable;

/**
 * Laravel's migrator, plus access to the SQL a migration would run without running it (`migrate --pretend`).
 */
final class PreflightMigrator extends Migrator
{
    /**
     * The connection name a migration file targets, or null for the default connection.
     */
    public function connectionFor(string $path): ?string
    {
        $migration = $this->resolvePath($path);
        $connection = method_exists($migration, 'getConnection') ? $migration->getConnection() : null;

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    public function shouldRun(string $path): bool
    {
        $migration = $this->resolvePath($path);

        // Migration::shouldRun() exists from Laravel 12.
        return ! $migration instanceof Migration || ! (new \ReflectionClass($migration))->hasMethod('shouldRun') || $migration->shouldRun();
    }

    /**
     * The statements `up()` would execute, with bindings substituted.
     *
     * @return list<string>
     */
    public function pretend(string $path): array
    {
        $migration = $this->resolvePath($path);
        $connection = $this->resolveConnection($this->connectionFor($path));

        $statements = [];
        foreach ($this->getQueries($migration, 'up') as $query) {
            $sql = is_array($query) && is_string($query['query'] ?? null) ? $query['query'] : '';
            $bindings = is_array($query) && is_array($query['bindings'] ?? null) ? $query['bindings'] : [];
            if (trim($sql) === '') {
                continue;
            }
            $statements[] = $bindings === [] ? $sql : $this->substitute($connection, $sql, $bindings);
        }

        return $statements;
    }

    /**
     * @param  array<mixed>  $bindings
     */
    private function substitute(Connection $connection, string $sql, array $bindings): string
    {
        try {
            return $connection->getQueryGrammar()->substituteBindingsIntoRawSql($sql, $connection->prepareBindings($bindings));
        } catch (Throwable) {
            // Data statements only need their shape (verb, table, WHERE) for analysis.
            return $sql;
        }
    }
}
