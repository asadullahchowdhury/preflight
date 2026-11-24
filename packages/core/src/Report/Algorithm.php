<?php

declare(strict_types=1);

namespace Preflight\Core\Report;

/**
 * The InnoDB online-DDL algorithm MySQL will choose for an ALTER (the cheapest one it supports).
 */
enum Algorithm: string
{
    case Instant = 'INSTANT';
    case Inplace = 'INPLACE';
    case Copy = 'COPY';

    public function rank(): int
    {
        return match ($this) {
            self::Instant => 0,
            self::Inplace => 1,
            self::Copy => 2,
        };
    }

    public function max(self $other): self
    {
        return $other->rank() > $this->rank() ? $other : $this;
    }
}
