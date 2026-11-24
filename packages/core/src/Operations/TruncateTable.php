<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

final class TruncateTable extends Operation
{
    public function label(): string
    {
        return 'TRUNCATE TABLE '.self::q((string) $this->table);
    }
}
