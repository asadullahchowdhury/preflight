<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

final class AlterIndexVisibility extends Operation
{
    public function __construct(string $table, public readonly string $name, public readonly bool $visible)
    {
        parent::__construct($table);
    }

    public function label(): string
    {
        return 'ALTER INDEX '.self::q($this->name).($this->visible ? ' VISIBLE' : ' INVISIBLE');
    }
}
