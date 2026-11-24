<?php

declare(strict_types=1);

namespace Preflight\Core\Schema;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;

/**
 * The schema as it exists before the migration runs (typically from SHOW CREATE TABLE).
 */
final class SchemaSnapshot
{
    /** @var array<string, Table> keyed by lower-cased table name */
    private array $tables = [];

    /**
     * @param  list<Table>  $tables
     */
    public function __construct(array $tables = [])
    {
        foreach ($tables as $table) {
            $this->tables[strtolower($table->name)] = $table;
        }
    }

    /**
     * Build a snapshot from one or more CREATE TABLE statements. Other statements are ignored.
     */
    public static function fromSql(string $sql): self
    {
        if (trim($sql) === '') {
            return new self;
        }

        $tables = [];
        $parser = new Parser($sql);
        foreach ($parser->statements as $statement) {
            if ($statement instanceof CreateStatement && $statement->options?->has('TABLE')) {
                $table = DefinitionReader::table($statement);
                if ($table !== null) {
                    $tables[] = $table;
                }
            }
        }

        return new self($tables);
    }

    public function table(string $name): ?Table
    {
        return $this->tables[strtolower($name)] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->tables[strtolower($name)]);
    }

    public function put(Table $table): void
    {
        $this->tables[strtolower($table->name)] = $table;
    }

    public function remove(string $name): void
    {
        unset($this->tables[strtolower($name)]);
    }

    /**
     * @return list<Table>
     */
    public function tables(): array
    {
        return array_values($this->tables);
    }
}
