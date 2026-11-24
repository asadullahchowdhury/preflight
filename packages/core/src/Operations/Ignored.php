<?php

declare(strict_types=1);

namespace Preflight\Core\Operations;

/**
 * Statements with no schema or locking impact worth reporting (SELECT, SHOW, transaction control…).
 */
final class Ignored extends Operation
{
    public function __construct(public readonly string $verb)
    {
        parent::__construct(null);
    }

    public function label(): string
    {
        return $this->verb;
    }
}
