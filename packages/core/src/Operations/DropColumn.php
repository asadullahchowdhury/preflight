<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

final class DropColumn extends Operation
{
    public function __construct(string $table, public readonly string $column)
    {
        parent::__construct($table);
    }

    public function label(): string
    {
        return 'DROP COLUMN '.self::q($this->column);
    }
}
