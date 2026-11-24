<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

final class AddCheck extends Operation
{
    public function __construct(string $table, public readonly ?string $name = null)
    {
        parent::__construct($table);
    }

    public function label(): string
    {
        return 'ADD CHECK'.($this->name !== null ? ' '.self::q($this->name) : '');
    }
}
