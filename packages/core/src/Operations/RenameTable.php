<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

final class RenameTable extends Operation
{
    public function __construct(string $from, public readonly string $to)
    {
        parent::__construct($from);
    }

    public function label(): string
    {
        return 'RENAME TABLE '.self::q((string) $this->table).' TO '.self::q($this->to);
    }
}
