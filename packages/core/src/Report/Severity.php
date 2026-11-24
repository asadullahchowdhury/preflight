<?php

declare(strict_types=1);

namespace Preflight\Core\Report;

enum Severity: string
{
    case Info = 'info';
    case Warn = 'warn';
    case Danger = 'danger';

    public function rank(): int
    {
        return match ($this) {
            self::Info => 0,
            self::Warn => 1,
            self::Danger => 2,
        };
    }

    public function max(self $other): self
    {
        return $other->rank() > $this->rank() ? $other : $this;
    }
}
