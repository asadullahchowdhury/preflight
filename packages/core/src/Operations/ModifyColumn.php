<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

use Preflight\Core\Schema\Column;

/**
 * MODIFY COLUMN, or CHANGE COLUMN (which may also rename). Laravel's ->change() compiles to MODIFY.
 */
final class ModifyColumn extends Operation
{
    public function __construct(
        string $table,
        public readonly string $from,
        public readonly Column $column,
        public readonly ?string $position = null,
        public readonly ?string $after = null,
        public readonly bool $viaChange = false,
    ) {
        parent::__construct($table);
    }

    public function renames(): bool
    {
        return strcasecmp($this->from, $this->column->name) !== 0;
    }

    public function label(): string
    {
        if ($this->viaChange) {
            return 'CHANGE COLUMN '.self::q($this->from).($this->renames() ? ' → '.self::q($this->column->name) : '');
        }

        return 'MODIFY COLUMN '.self::q($this->column->name);
    }
}
