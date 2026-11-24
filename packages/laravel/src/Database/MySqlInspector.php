<?php

declare(strict_types=1);

namespace Preflight\Laravel\Database;

use Illuminate\Database\Connection;
use Preflight\Core\Schema\SchemaSnapshot;
use Preflight\Laravel\PreflightException;
use Throwable;

/**
 * Read-only queries against the MySQL server: version, session settings, SHOW CREATE TABLE, table sizes.
 */
final class MySqlInspector
{
    private ?string $version = null;

    public function __construct(private readonly Connection $connection)
    {
        $driver = $connection->getDriverName();
        if ($driver === 'mariadb') {
            throw new PreflightException("Connection [{$connection->getName()}] is MariaDB. Preflight supports MySQL 8.0 and later only; MariaDB's online DDL differs.");
        }
        if ($driver !== 'mysql') {
            throw new PreflightException("Connection [{$connection->getName()}] uses the [{$driver}] driver. Preflight supports MySQL 8.0 and later only.");
        }
    }

    public function version(): string
    {
        if ($this->version === null) {
            $version = $this->scalar('select version()');
            if (stripos($version, 'mariadb') !== false) {
                throw new PreflightException("The server behind [{$this->connection->getName()}] is MariaDB ({$version}). Preflight supports MySQL 8.0 and later only.");
            }
            $this->version = $version;
        }

        return $this->version;
    }

    public function foreignKeyChecks(): bool
    {
        return $this->scalar('select @@session.foreign_key_checks') !== '0';
    }

    public function defaultCharset(): string
    {
        $configured = $this->connection->getConfig('charset');

        return is_string($configured) && $configured !== '' ? $configured : $this->scalar('select @@character_set_database');
    }

    /**
     * @return list<string> base table names in the connection's database
     */
    public function tables(): array
    {
        $tables = [];
        foreach ($this->connection->select("show full tables where Table_type = 'BASE TABLE'") as $row) {
            $name = array_values((array) $row)[0] ?? null;
            if (is_string($name)) {
                $tables[] = $name;
            }
        }

        return $tables;
    }

    /**
     * SHOW CREATE TABLE for every table, as one SQL script.
     */
    public function createStatements(): string
    {
        $statements = [];
        foreach ($this->tables() as $table) {
            $row = $this->connection->selectOne('show create table '.$this->quote($table));
            $create = $row !== null ? (((array) $row)['Create Table'] ?? null) : null;
            if (is_string($create)) {
                $statements[] = $create.';';
            }
        }

        return implode("\n\n", $statements);
    }

    public function snapshot(): SchemaSnapshot
    {
        return SchemaSnapshot::fromSql($this->createStatements());
    }

    /**
     * Sizes of every base table, from information_schema. Numbers only, never row data.
     *
     * @return array<string, array{rows: int, data_bytes: int, index_bytes: int}>
     */
    public function tableStats(): array
    {
        try {
            // information_schema caches sizes for up to 24h by default; ask for current values.
            $this->connection->statement('set session information_schema_stats_expiry = 0');
        } catch (Throwable) {
            // Not available on every server; cached values are still useful.
        }

        $rows = $this->connection->select(
            "select TABLE_NAME as name, TABLE_ROWS as `rows`, DATA_LENGTH as data_bytes, INDEX_LENGTH as index_bytes
             from information_schema.TABLES
             where TABLE_SCHEMA = database() and TABLE_TYPE = 'BASE TABLE'
             order by TABLE_NAME"
        );

        $stats = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $name = $row['name'] ?? null;
            if (! is_string($name)) {
                continue;
            }
            $stats[$name] = [
                'rows' => self::int($row['rows'] ?? 0),
                'data_bytes' => self::int($row['data_bytes'] ?? 0),
                'index_bytes' => self::int($row['index_bytes'] ?? 0),
            ];
        }

        return $stats;
    }

    private function scalar(string $sql): string
    {
        $row = $this->connection->selectOne($sql);
        $value = $row !== null ? (array_values((array) $row)[0] ?? null) : null;

        return is_scalar($value) ? (string) $value : '';
    }

    private function quote(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
