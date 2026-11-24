<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

use Preflight\Core\Schema\Column;

final class AddColumn extends Operation
{
    public function __construct(
        string $table,
        public readonly Column $column,
        public readonly ?string $position = null,
        public readonly ?string $after = null,
    ) {
        parent::__construct($table);
    }

    public function label(): string
    {
        return 'ADD COLUMN '.self::q($this->column->name);
    }
}
