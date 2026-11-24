<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

/**
 * A table option in ALTER TABLE: ENGINE, ROW_FORMAT, COMMENT, AUTO_INCREMENT, DEFAULT CHARSET, FORCE…
 */
final class TableOption extends Operation
{
    public function __construct(string $table, public readonly string $option, public readonly ?string $value = null)
    {
        parent::__construct($table);
    }

    public function label(): string
    {
        return $this->option.($this->value !== null ? '='.$this->value : '');
    }
}
