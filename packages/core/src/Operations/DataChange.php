<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

/**
 * INSERT / UPDATE / DELETE / REPLACE inside a migration.
 */
final class DataChange extends Operation
{
    public function __construct(?string $table, public readonly string $verb, public readonly bool $hasWhere = true)
    {
        parent::__construct($table);
    }

    public function label(): string
    {
        return $this->verb.($this->table !== null ? ' '.self::q($this->table) : '');
    }
}
