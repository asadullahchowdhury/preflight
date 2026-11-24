<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

final class RenameIndex extends Operation
{
    public function __construct(string $table, public readonly string $from, public readonly string $to)
    {
        parent::__construct($table);
    }

    public function label(): string
    {
        return 'RENAME INDEX '.self::q($this->from).' TO '.self::q($this->to);
    }
}
