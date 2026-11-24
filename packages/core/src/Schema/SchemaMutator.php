<?php

declare(strict_types=1);

namespace Preflight\Core\Schema;

use Preflight\Core\Operations\AddColumn;
use Preflight\Core\Operations\AddForeignKey;
use Preflight\Core\Operations\AddIndex;
use Preflight\Core\Operations\AlterColumn;
use Preflight\Core\Operations\CreateTable;
use Preflight\Core\Operations\DropColumn;
use Preflight\Core\Operations\DropForeignKey;
use Preflight\Core\Operations\DropIndex;
use Preflight\Core\Operations\DropPrimaryKey;
use Preflight\Core\Operations\DropTable;
use Preflight\Core\Operations\ModifyColumn;
use Preflight\Core\Operations\Operation;
use Preflight\Core\Operations\RenameColumn;
use Preflight\Core\Operations\RenameIndex;
use Preflight\Core\Operations\RenameTable;
use Preflight\Core\Operations\TableOption;

/**
 * Applies operations to a working snapshot so later statements in the same migration batch see the
 * schema as it will be at that point (e.g. an index added to a table created two statements earlier).
 */
final class SchemaMutator
{
    public function apply(SchemaSnapshot $schema, Operation $op): void
    {
        if ($op instanceof CreateTable) {
            $schema->put($op->definition ?? new Table((string) $op->table));

            return;
        }
        if ($op instanceof DropTable) {
            $schema->remove((string) $op->table);

            return;
        }
        if ($op instanceof RenameTable) {
            $table = $schema->table((string) $op->table);
            if ($table !== null) {
                $schema->remove($table->name);
                $schema->put($table->renamed($op->to));
            }

            return;
        }

        $table = $op->table !== null ? $schema->table($op->table) : null;
        if ($table === null) {
            return;
        }

        $updated = $this->alter($table, $op);
        if ($updated !== null) {
            $schema->put($updated);
        }
    }

    private function alter(Table $table, Operation $op): ?Table
    {
        switch (true) {
            case $op instanceof AddColumn:
                return $table->with(columns: $this->place($table->columns, $op->column, $op->position, $op->after));

            case $op instanceof DropColumn:
                $columns = array_values(array_filter($table->columns, static fn (Column $c) => strcasecmp($c->name, $op->column) !== 0));
                $indexes = [];
                foreach ($table->indexes as $index) {
                    $remaining = array_values(array_filter($index->columns, static fn (string $c) => strcasecmp($c, $op->column) !== 0));
                    if ($remaining !== []) {
                        $indexes[] = new Index($index->name, $index->type, $remaining);
                    }
                }

                return $table->with(columns: $columns, indexes: $indexes);

            case $op instanceof RenameColumn:
                return $this->renameColumn($table, $op->from, $op->to, null);

            case $op instanceof ModifyColumn:
                $renamed = $this->renameColumn($table, $op->from, $op->column->name, $op->column);
                if ($op->position === null) {
                    return $renamed;
                }
                $others = array_values(array_filter($renamed->columns, static fn (Column $c) => strcasecmp($c->name, $op->column->name) !== 0));

                return $renamed->with(columns: $this->place($others, $op->column, $op->position, $op->after));

            case $op instanceof AlterColumn:
                if ($op->action !== AlterColumn::SET_DEFAULT && $op->action !== AlterColumn::DROP_DEFAULT) {
                    return null;
                }
                $columns = array_map(
                    static fn (Column $c) => strcasecmp($c->name, $op->column) === 0 ? $c->withDefault($op->default) : $c,
                    $table->columns,
                );

                return $table->with(columns: array_values($columns));

            case $op instanceof AddIndex:
                return $table->with(indexes: [...$table->indexes, $op->index]);

            case $op instanceof DropIndex:
                return $table->with(indexes: array_values(array_filter($table->indexes, static fn (Index $i) => strcasecmp($i->name, $op->name) !== 0)));

            case $op instanceof DropPrimaryKey:
                return $table->with(indexes: array_values(array_filter($table->indexes, static fn (Index $i) => $i->type !== Index::PRIMARY)));

            case $op instanceof RenameIndex:
                $indexes = array_map(static fn (Index $i) => strcasecmp($i->name, $op->from) === 0 ? $i->withName($op->to) : $i, $table->indexes);

                return $table->with(indexes: array_values($indexes));

            case $op instanceof AddForeignKey:
                return $table->with(foreignKeys: [...$table->foreignKeys, $op->name]);

            case $op instanceof DropForeignKey:
                return $table->with(foreignKeys: array_values(array_filter($table->foreignKeys, static fn (string $fk) => strcasecmp($fk, $op->name) !== 0)));

            case $op instanceof TableOption:
                return match ($op->option) {
                    'ENGINE' => $table->with(engine: $op->value),
                    'ROW_FORMAT' => $table->with(rowFormat: $op->value !== null ? strtoupper($op->value) : null),
                    'DEFAULT CHARSET' => $table->with(charset: $op->value),
                    default => null,
                };
        }

        return null;
    }

    private function renameColumn(Table $table, string $from, string $to, ?Column $definition): Table
    {
        $columns = array_map(
            static fn (Column $c) => strcasecmp($c->name, $from) === 0 ? ($definition ?? $c->withName($to)) : $c,
            $table->columns,
        );
        $indexes = array_map(
            static fn (Index $i) => $i->mapColumns(static fn (string $c) => strcasecmp($c, $from) === 0 ? $to : $c),
            $table->indexes,
        );

        return $table->with(columns: array_values($columns), indexes: array_values($indexes));
    }

    /**
     * @param  list<Column>  $columns
     * @return list<Column>
     */
    private function place(array $columns, Column $column, ?string $position, ?string $after): array
    {
        if ($position === 'FIRST') {
            return [$column, ...$columns];
        }
        if ($position === 'AFTER' && $after !== null) {
            $result = [];
            $placed = false;
            foreach ($columns as $existing) {
                $result[] = $existing;
                if (! $placed && strcasecmp($existing->name, $after) === 0) {
                    $result[] = $column;
                    $placed = true;
                }
            }
            if (! $placed) {
                $result[] = $column;
            }

            return $result;
        }

        return [...$columns, $column];
    }
}
