<?php

declare(strict_types=1);

namespace Preflight\Core\Report;

/**
 * The weakest LOCK clause MySQL accepts for the operation.
 * SHARED and EXCLUSIVE both block writes for the whole operation.
 */
enum LockLevel: string
{
    case None = 'NONE';
    case Shared = 'SHARED';
    case Exclusive = 'EXCLUSIVE';

    public function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::Shared => 1,
            self::Exclusive => 2,
        };
    }

    public function max(self $other): self
    {
        return $other->rank() > $this->rank() ? $other : $this;
    }

    public function blocksWrites(): bool
    {
        return $this !== self::None;
    }
}
