<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

final class DropForeignKey extends Operation
{
    public function __construct(string $table, public readonly string $name)
    {
        parent::__construct($table);
    }

    public function label(): string
    {
        return 'DROP FOREIGN KEY '.self::q($this->name);
    }
}
