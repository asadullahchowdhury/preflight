<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

/**
 * ALTER COLUMN … SET DEFAULT / DROP DEFAULT / SET VISIBLE / SET INVISIBLE.
 */
final class AlterColumn extends Operation
{
    public const SET_DEFAULT = 'SET DEFAULT';

    public const DROP_DEFAULT = 'DROP DEFAULT';

    public const SET_VISIBLE = 'SET VISIBLE';

    public const SET_INVISIBLE = 'SET INVISIBLE';

    public function __construct(
        string $table,
        public readonly string $column,
        public readonly string $action,
        public readonly ?string $default = null,
    ) {
        parent::__construct($table);
    }

    public function label(): string
    {
        return 'ALTER COLUMN '.self::q($this->column).' '.$this->action;
    }
}
