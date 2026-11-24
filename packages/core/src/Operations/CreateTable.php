<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

use Preflight\Core\Schema\Table;

final class CreateTable extends Operation
{
    public function __construct(string $table, public readonly ?Table $definition)
    {
        parent::__construct($table);
    }

    public function label(): string
    {
        return 'CREATE TABLE '.self::q((string) $this->table);
    }
}
