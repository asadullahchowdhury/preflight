<?php

declare(strict_types=1);

namespace Preflight\Core\Schema;

use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Components\OptionsArray;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;

/**
 * Turns phpmyadmin/sql-parser CREATE TABLE components into Preflight schema objects.
 */
final class DefinitionReader
{
    /**
     * Parse a bare column definition such as "`note` varchar(255) null default 'x'".
     */
    public static function column(string $definition): ?Column
    {
        $parser = new Parser('CREATE TABLE `__preflight` ('.$definition.')');
        $statement = $parser->statements[0] ?? null;
        if (! $statement instanceof CreateStatement || ! is_array($statement->fields) || $parser->errors !== []) {
            return null;
        }

        foreach ($statement->fields as $field) {
            if ($field->type !== null && $field->name !== null) {
                return self::columnFromField($field);
            }
        }

        return null;
    }

    public static function table(CreateStatement $statement): ?Table
    {
        $name = $statement->name instanceof Expression ? ($statement->name->table ?? null) : null;
        if ($name === null || ! is_array($statement->fields)) {
            return null;
        }

        $columns = [];
        $indexes = [];
        $foreignKeys = [];

        foreach ($statement->fields as $field) {
            if ($field->type !== null && $field->name !== null) {
                $column = self::columnFromField($field);
                $columns[] = $column;
                if ($column->inlinePrimaryKey) {
                    $indexes[] = new Index('PRIMARY', Index::PRIMARY, [$column->name]);
                } elseif ($column->inlineUnique) {
                    $indexes[] = new Index($column->name, Index::UNIQUE, [$column->name]);
                }

                continue;
            }

            if ($field->key === null) {
                continue; // CHECK constraints and other definitions we don't model.
            }

            $type = self::indexType($field->key->type);
            $keyColumns = [];
            foreach ($field->key->columns as $keyColumn) {
                if (isset($keyColumn['name'])) {
                    $keyColumns[] = (string) $keyColumn['name'];
                }
            }

            // sql-parser documents Key::$name as string, but leaves it null for unnamed keys.
            $keyName = self::nonEmpty($field->key->name);

            if ($type === 'FOREIGN') {
                $foreignKeys[] = self::nonEmpty($field->name) ?? $keyName ?? implode('_', $keyColumns).'_foreign';

                continue;
            }

            $indexName = $type === Index::PRIMARY ? 'PRIMARY' : ($keyName ?? $keyColumns[0] ?? 'index');
            $indexes[] = new Index($indexName, $type, $keyColumns);
        }

        $options = $statement->entityOptions;

        return new Table(
            name: $name,
            columns: $columns,
            indexes: $indexes,
            foreignKeys: $foreignKeys,
            engine: self::stringOption($options, 'ENGINE') ?? 'InnoDB',
            charset: self::stringOption($options, 'DEFAULT CHARSET', 'CHARSET', 'DEFAULT CHARACTER SET', 'CHARACTER SET'),
            collation: self::stringOption($options, 'DEFAULT COLLATE', 'COLLATE'),
            rowFormat: ($rowFormat = self::stringOption($options, 'ROW_FORMAT')) !== null ? strtoupper($rowFormat) : null,
        );
    }

    public static function indexType(string $keyType): string
    {
        $keyType = strtoupper($keyType);

        return match (true) {
            str_starts_with($keyType, 'PRIMARY') => Index::PRIMARY,
            str_starts_with($keyType, 'UNIQUE') => Index::UNIQUE,
            str_starts_with($keyType, 'FULLTEXT') => Index::FULLTEXT,
            str_starts_with($keyType, 'SPATIAL') => Index::SPATIAL,
            str_starts_with($keyType, 'FOREIGN') => 'FOREIGN',
            default => Index::INDEX,
        };
    }

    private static function columnFromField(CreateDefinition $field): Column
    {
        $type = $field->type;
        assert($type !== null);
        $options = $field->options ?? new OptionsArray;
        $typeOptions = $type->options;

        $generated = self::exprOption($options, 'AS');

        return new Column(
            name: (string) $field->name,
            type: self::normalizeType(strtoupper($type->name)),
            params: array_values(array_map(static fn ($p) => (string) $p, $type->parameters)),
            unsigned: (bool) $typeOptions->has('UNSIGNED'),
            nullable: ! $options->has('NOT NULL') && ! $options->has('PRIMARY KEY'),
            default: self::normalizeDefault(self::exprOption($options, 'DEFAULT')),
            autoIncrement: (bool) $options->has('AUTO_INCREMENT'),
            charset: self::stringOption($typeOptions, 'CHARACTER SET', 'CHARSET') ?? self::stringOption($options, 'CHARACTER SET', 'CHARSET'),
            collation: self::stringOption($typeOptions, 'COLLATE') ?? self::stringOption($options, 'COLLATE'),
            comment: self::stringOption($options, 'COMMENT'),
            onUpdate: self::normalizeDefault(self::exprOption($options, 'ON UPDATE')),
            generatedExpression: $generated !== null ? self::normalizeExpression($generated) : null,
            generatedStored: (bool) $options->has('STORED') || (bool) $options->has('PERSISTENT'),
            invisible: (bool) $options->has('INVISIBLE'),
            inlinePrimaryKey: (bool) $options->has('PRIMARY KEY') || (bool) $options->has('PRIMARY'),
            inlineUnique: (bool) $options->has('UNIQUE') || (bool) $options->has('UNIQUE KEY'),
        );
    }

    private static function nonEmpty(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function normalizeType(string $type): string
    {
        return match ($type) {
            'INTEGER' => 'INT',
            'BOOL', 'BOOLEAN' => 'TINYINT',
            'DEC', 'NUMERIC', 'FIXED' => 'DECIMAL',
            default => $type,
        };
    }

    private static function stringOption(?OptionsArray $options, string ...$names): ?string
    {
        if ($options === null) {
            return null;
        }
        foreach ($names as $name) {
            $value = $options->has($name);
            if (is_string($value) && $value !== '') {
                return trim($value, "'\"` ");
            }
        }

        return null;
    }

    private static function exprOption(OptionsArray $options, string $name): ?string
    {
        $expr = $options->has($name, true);
        if ($expr instanceof Expression) {
            return $expr->expr;
        }

        return is_string($expr) && $expr !== '' ? $expr : null;
    }

    /**
     * Normalise defaults so equivalent spellings compare equal: '0' vs 0, now() vs CURRENT_TIMESTAMP, NULL vs none.
     */
    public static function normalizeDefault(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if (strcasecmp($value, 'NULL') === 0) {
            return null;
        }
        if (preg_match("/^'(.*)'$/s", $value, $m) || preg_match('/^"(.*)"$/s', $value, $m)) {
            return "'".str_replace(["''", "\\'"], "'", $m[1])."'";
        }
        if (is_numeric($value)) {
            return "'".$value."'";
        }

        $upper = strtoupper(preg_replace('/\s+/', '', $value) ?? $value);
        if (preg_match('/^(CURRENT_TIMESTAMP|NOW|LOCALTIMESTAMP|LOCALTIME)(\((\d*)\))?$/', $upper, $m)) {
            $precision = $m[3] ?? '';

            return $precision !== '' && $precision !== '0' ? "CURRENT_TIMESTAMP({$precision})" : 'CURRENT_TIMESTAMP';
        }

        return $upper;
    }

    private static function normalizeExpression(string $expression): string
    {
        return strtolower(str_replace(['`', ' '], '', $expression));
    }
}
