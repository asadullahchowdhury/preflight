<?php

declare(strict_types=1);

namespace Preflight\Core\Rules;

use Preflight\Core\Operations\AddCheck;
use Preflight\Core\Operations\AddForeignKey;
use Preflight\Core\Operations\DropForeignKey;
use Preflight\Core\Operations\Operation;
use Preflight\Core\Report\Classification;

final class ConstraintRules implements Rule
{
    public function supports(Operation $operation): bool
    {
        return $operation instanceof AddForeignKey
            || $operation instanceof DropForeignKey
            || $operation instanceof AddCheck;
    }

    public function evaluate(Operation $operation, RuleContext $context): Verdict
    {
        if ($operation instanceof AddForeignKey) {
            if ($context->server->foreignKeyChecks) {
                return new Verdict('add-foreign-key', Classification::copy(),
                    "Adding foreign key `{$operation->name}` with foreign_key_checks enabled forces ALGORITHM=COPY: the table is copied and writes are blocked until it finishes.",
                    Advice::foreignKeyWithoutChecks((string) $operation->table, $operation->columns, $operation->references));
            }

            return new Verdict('add-foreign-key', Classification::inplaceMetadata(),
                "Adds foreign key `{$operation->name}` in place (foreign_key_checks is off, so existing rows are not validated).",
                'Verify there are no orphan rows; MySQL did not check them.');
        }

        if ($operation instanceof DropForeignKey) {
            return new Verdict('drop-foreign-key', Classification::inplaceMetadata(),
                "Drops foreign key `{$operation->name}` in place (metadata only).");
        }

        if (! $context->server->enforcesCheckConstraints()) {
            return new Verdict('add-check', Classification::instant(),
                "MySQL {$context->server->version} parses CHECK constraints but ignores them (they are enforced from 8.0.16), so this is metadata only and the constraint is NOT enforced.",
                'Validate in application code until the server is upgraded to 8.0.16 or later.');
        }

        return new Verdict('add-check', Classification::copy(),
            'Adding a CHECK constraint copies the table (ALGORITHM=COPY) to validate every row, blocking writes.',
            Advice::blocking('Validate in application code instead, or schedule a maintenance window.'));
    }
}
