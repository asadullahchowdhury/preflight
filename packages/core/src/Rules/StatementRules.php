<?php

declare(strict_types=1);

namespace Preflight\Core\Rules;

use Preflight\Core\Operations\DataChange;
use Preflight\Core\Operations\Operation;
use Preflight\Core\Operations\Unknown;
use Preflight\Core\Report\Severity;

/**
 * Data changes and anything the parser could not classify.
 */
final class StatementRules implements Rule
{
    public function supports(Operation $operation): bool
    {
        return $operation instanceof DataChange || $operation instanceof Unknown;
    }

    public function evaluate(Operation $operation, RuleContext $context): Verdict
    {
        if ($operation instanceof Unknown) {
            return new Verdict('unknown-statement', null,
                "Preflight can't analyse this yet ({$operation->reason}) Review it manually: some ALTERs copy the table and block writes.",
                fixedSeverity: Severity::Warn);
        }

        assert($operation instanceof DataChange);
        $table = $operation->table !== null ? "`{$operation->table}`" : 'the table';

        if (in_array($operation->verb, ['UPDATE', 'DELETE'], true) && ! $operation->hasWhere) {
            return new Verdict('data-change', null,
                "{$operation->verb} without WHERE touches every row of {$table} in a single transaction: rows stay locked until it commits and replicas lag.",
                Advice::batchedBackfill($operation->table),
                fixedSeverity: Severity::Warn);
        }

        return new Verdict('data-change', null,
            "{$operation->verb} on {$table} runs inside the deploy.",
            'Keep data changes small; large backfills belong in a queued job, not a migration.',
            fixedSeverity: Severity::Info);
    }
}
