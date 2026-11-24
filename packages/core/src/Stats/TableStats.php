<?php

declare(strict_types=1);

namespace Preflight\Core\Stats;

/**
 * Size of one production table, from information_schema.TABLES. Numbers only, never row data.
 */
final class TableStats
{
    public function __construct(
        public readonly string $table,
        /** TABLE_ROWS: an InnoDB estimate, can be off by 30–50%. */
        public readonly int $rows,
        public readonly int $dataBytes,
        public readonly int $indexBytes,
    ) {}

    public function totalBytes(): int
    {
        return $this->dataBytes + $this->indexBytes;
    }
}
