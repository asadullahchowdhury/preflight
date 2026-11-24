<?php

declare(strict_types=1);

namespace Preflight\Core\Rules;

use Preflight\Core\Operations\Operation;
use Preflight\Core\Schema\Table;
use Preflight\Core\Server\ServerProfile;
use Preflight\Core\Sql\ParsedStatement;

final class RuleContext
{
    public function __construct(
        public readonly ServerProfile $server,
        public readonly ParsedStatement $statement,
        /** The table as it was before this statement, if known. */
        public readonly ?Table $table,
    ) {}

    /**
     * @template T of Operation
     *
     * @param  class-string<T>  $class
     */
    public function statementHas(string $class, ?callable $filter = null): bool
    {
        foreach ($this->statement->operations as $operation) {
            if ($operation instanceof $class && ($filter === null || $filter($operation))) {
                return true;
            }
        }

        return false;
    }
}
