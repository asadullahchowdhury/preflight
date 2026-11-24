<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

final class SetVariable extends Operation
{
    public function __construct(public readonly string $name, public readonly string $value)
    {
        parent::__construct(null);
    }

    public function label(): string
    {
        return 'SET '.$this->name.' = '.$this->value;
    }
}
