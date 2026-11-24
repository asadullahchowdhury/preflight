<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

final class DropPrimaryKey extends Operation
{
    public function label(): string
    {
        return 'DROP PRIMARY KEY';
    }
}
