<?php

declare(strict_types=1);

namespace Preflight\Core\Schema;

/**
 * Immutable table definition. Mutators return a new instance.
 */
final class Table
{
    /**
     * @param  list<Column>  $columns
     * @param  list<Index>  $indexes
     * @param  list<string>  $foreignKeys  Constraint names.
     */
    public function __construct(
        public readonly string $name,
        public readonly array $columns = [],
        public readonly array $indexes = [],
        public readonly array $foreignKeys = [],
        public readonly string $engine = 'InnoDB',
        public readonly ?string $charset = null,
        public readonly ?string $collation = null,
        public readonly ?string $rowFormat = null,
    ) {}

    public function column(string $name): ?Column
    {
        foreach ($this->columns as $column) {
            if (strcasecmp($column->name, $name) === 0) {
                return $column;
            }
        }

        return null;
    }

    public function columnPosition(string $name): ?int
    {
        foreach ($this->columns as $i => $column) {
            if (strcasecmp($column->name, $name) === 0) {
                return $i;
            }
        }

        return null;
    }

    public function index(string $name): ?Index
    {
        foreach ($this->indexes as $index) {
            if (strcasecmp($index->name, $name) === 0) {
                return $index;
            }
        }

        return null;
    }

    public function primaryKey(): ?Index
    {
        foreach ($this->indexes as $index) {
            if ($index->type === Index::PRIMARY) {
                return $index;
            }
        }

        return null;
    }

    public function fulltextIndex(): ?Index
    {
        foreach ($this->indexes as $index) {
            if ($index->type === Index::FULLTEXT) {
                return $index;
            }
        }

        return null;
    }

    public function hasFulltextIndex(): bool
    {
        foreach ($this->indexes as $index) {
            if ($index->type === Index::FULLTEXT) {
                return true;
            }
        }

        return false;
    }

    public function isColumnIndexed(string $column): bool
    {
        foreach ($this->indexes as $index) {
            if ($index->covers($column)) {
                return true;
            }
        }

        return false;
    }

    public function isCompressed(): bool
    {
        return strtoupper((string) $this->rowFormat) === 'COMPRESSED';
    }

    /** Charset a column uses when it doesn't declare one. */
    public function effectiveCharset(Column $column, string $serverDefault): string
    {
        if ($column->charset !== null) {
            return strtolower($column->charset);
        }
        if ($column->collation !== null) {
            return Charset::fromCollation($column->collation);
        }
        if ($this->charset !== null) {
            return strtolower($this->charset);
        }
        if ($this->collation !== null) {
            return Charset::fromCollation($this->collation);
        }

        return $serverDefault;
    }

    public function renamed(string $name): self
    {
        return new self($name, $this->columns, $this->indexes, $this->foreignKeys, $this->engine, $this->charset, $this->collation, $this->rowFormat);
    }

    /**
     * @param  list<Column>  $columns
     * @param  list<Index>  $indexes
     * @param  list<string>  $foreignKeys
     */
    public function with(?array $columns = null, ?array $indexes = null, ?array $foreignKeys = null, ?string $engine = null, ?string $charset = null, ?string $rowFormat = null): self
    {
        return new self(
            $this->name,
            $columns ?? $this->columns,
            $indexes ?? $this->indexes,
            $foreignKeys ?? $this->foreignKeys,
            $engine ?? $this->engine,
            $charset ?? $this->charset,
            $this->collation,
            $rowFormat ?? $this->rowFormat,
        );
    }
}
