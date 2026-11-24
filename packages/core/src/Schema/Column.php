<?php

declare(strict_types=1);

namespace Preflight\Core\Schema;

/**
 * A column definition as written in CREATE TABLE / ALTER TABLE.
 */
final class Column
{
    /**
     * @param  list<string>  $params  Type parameters, e.g. ['255'] for VARCHAR(255) or ["'a'", "'b'"] for ENUM.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array $params = [],
        public readonly bool $unsigned = false,
        public readonly bool $nullable = true,
        public readonly ?string $default = null,
        public readonly bool $autoIncrement = false,
        public readonly ?string $charset = null,
        public readonly ?string $collation = null,
        public readonly ?string $comment = null,
        public readonly ?string $onUpdate = null,
        public readonly ?string $generatedExpression = null,
        public readonly bool $generatedStored = false,
        public readonly bool $invisible = false,
        public readonly bool $inlinePrimaryKey = false,
        public readonly bool $inlineUnique = false,
    ) {}

    public function isGenerated(): bool
    {
        return $this->generatedExpression !== null;
    }

    public function isVarLength(): bool
    {
        return in_array($this->type, ['VARCHAR', 'VARBINARY'], true);
    }

    public function isTextual(): bool
    {
        return in_array($this->type, ['CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'ENUM', 'SET'], true);
    }

    public function isInteger(): bool
    {
        return in_array($this->type, ['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT'], true);
    }

    public function length(): ?int
    {
        return isset($this->params[0]) && is_numeric($this->params[0]) ? (int) $this->params[0] : null;
    }

    public function withName(string $name): self
    {
        return new self(
            $name, $this->type, $this->params, $this->unsigned, $this->nullable, $this->default, $this->autoIncrement,
            $this->charset, $this->collation, $this->comment, $this->onUpdate, $this->generatedExpression,
            $this->generatedStored, $this->invisible, $this->inlinePrimaryKey, $this->inlineUnique,
        );
    }

    public function withDefault(?string $default): self
    {
        return new self(
            $this->name, $this->type, $this->params, $this->unsigned, $this->nullable, $default, $this->autoIncrement,
            $this->charset, $this->collation, $this->comment, $this->onUpdate, $this->generatedExpression,
            $this->generatedStored, $this->invisible, $this->inlinePrimaryKey, $this->inlineUnique,
        );
    }

    /** Human readable type, e.g. "varchar(255)" or "bigint unsigned". */
    public function typeLabel(): string
    {
        $label = strtolower($this->type);
        if ($this->params !== []) {
            $label .= '('.implode(',', $this->params).')';
        }

        return $this->unsigned ? $label.' unsigned' : $label;
    }
}
