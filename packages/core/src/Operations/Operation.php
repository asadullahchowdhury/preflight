<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

/**
 * One change a statement makes. ALTER TABLE statements produce one operation per clause.
 */
abstract class Operation
{
    public function __construct(
        public readonly ?string $table,
    ) {}

    /** Short human label, e.g. "ADD COLUMN `note`". */
    abstract public function label(): string;

    protected static function q(string $name): string
    {
        return '`'.$name.'`';
    }
}
