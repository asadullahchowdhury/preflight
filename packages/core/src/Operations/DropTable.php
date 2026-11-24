<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

final class DropTable extends Operation
{
    public function label(): string
    {
        return 'DROP TABLE '.self::q((string) $this->table);
    }
}
