<?php

declare(strict_types=1);

namespace Preflight\Core\Sql;

use Preflight\Core\Operations\Operation;

final class ParsedStatement
{
    public const ALTER = 'alter';

    public const CREATE_INDEX = 'create-index';

    public const DROP_INDEX = 'drop-index';

    public const CREATE_TABLE = 'create-table';

    public const DROP_TABLE = 'drop-table';

    public const RENAME_TABLE = 'rename-table';

    public const TRUNCATE = 'truncate';

    public const DML = 'dml';

    public const SET = 'set';

    public const IGNORED = 'ignored';

    public const UNKNOWN = 'unknown';

    /**
     * @param  list<Operation>  $operations
     */
    public function __construct(
        public readonly string $sql,
        public readonly string $kind,
        public readonly ?string $table,
        public readonly array $operations,
        public readonly ?string $explicitAlgorithm = null,
        public readonly ?string $explicitLock = null,
    ) {}

    /** Statements whose locking behaviour is decided by the online-DDL algorithm (and accept ALGORITHM=/LOCK=). */
    public function isOnlineDdl(): bool
    {
        return in_array($this->kind, [self::ALTER, self::CREATE_INDEX, self::DROP_INDEX], true);
    }
}
