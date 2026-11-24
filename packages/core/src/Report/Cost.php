<?php

declare(strict_types=1);

namespace Preflight\Core\Report;

/**
 * How much work the server does, independent of locking.
 */
enum Cost: string
{
    /** Data dictionary change only; finishes in milliseconds once the metadata lock is acquired. */
    case Metadata = 'metadata';

    /** Scans the table to build a secondary index; grows with table size. */
    case IndexBuild = 'index-build';

    /** Rewrites every row of the table. */
    case TableRebuild = 'table-rebuild';

    public function rank(): int
    {
        return match ($this) {
            self::Metadata => 0,
            self::IndexBuild => 1,
            self::TableRebuild => 2,
        };
    }

    public function max(self $other): self
    {
        return $other->rank() > $this->rank() ? $other : $this;
    }
}
