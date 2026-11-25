<?php

declare(strict_types=1);

namespace Preflight\Core\Rules;

use Preflight\Core\Operations\AddColumn;
use Preflight\Core\Operations\AlterColumn;
use Preflight\Core\Operations\DropColumn;
use Preflight\Core\Operations\ModifyColumn;
use Preflight\Core\Operations\Operation;
use Preflight\Core\Operations\RenameColumn;
use Preflight\Core\Report\Algorithm;
use Preflight\Core\Report\Classification;
use Preflight\Core\Report\LockLevel;
use Preflight\Core\Report\Severity;
use Preflight\Core\Schema\Table;
use Preflight\Core\Sql\ColumnDiff;

final class ColumnRules implements Rule
{
    public function supports(Operation $operation): bool
    {
        return $operation instanceof AddColumn
            || $operation instanceof DropColumn
            || $operation instanceof RenameColumn
            || $operation instanceof ModifyColumn
            || $operation instanceof AlterColumn;
    }

    public function evaluate(Operation $operation, RuleContext $context): Verdict
    {
        return match (true) {
            $operation instanceof AddColumn => $this->add($operation, $context),
            $operation instanceof DropColumn => $this->drop($operation, $context),
            $operation instanceof RenameColumn => $this->rename($operation, $context),
            $operation instanceof ModifyColumn => $this->modify($operation, $context),
            $operation instanceof AlterColumn => $this->alterColumn($operation, $context),
            default => throw new \LogicException('Unsupported operation'),
        };
    }

    private function add(AddColumn $op, RuleContext $context): Verdict
    {
        $server = $context->server;
        $table = $context->table;
        $column = $op->column;

        if ($column->isGenerated() && $column->generatedStored) {
            return new Verdict('add-column', Classification::copy(),
                'Adding a STORED generated column copies the whole table (ALGORITHM=COPY) and blocks writes until done.',
                'Use a VIRTUAL generated column (instant) if you only need it for reads, or '.Advice::ONLINE_SCHEMA_CHANGE.'.');
        }

        if ($table !== null && $table->hasFulltextIndex()) {
            return new Verdict('add-column', Classification::copy(),
                "`{$table->name}` has a FULLTEXT index, so MySQL can't add columns instantly or in place: it copies the table (ALGORITHM=COPY) and blocks writes.",
                Advice::fulltextBlocksAddColumn($table->name, $table->fulltextIndex()?->name, $table->fulltextIndex()?->columns[0] ?? null));
        }

        if ($column->autoIncrement || $column->inlinePrimaryKey) {
            return new Verdict('add-column', Classification::inplaceRebuild(LockLevel::Shared),
                'Adding an AUTO_INCREMENT / primary key column rebuilds the table and blocks writes (LOCK=SHARED).',
                Advice::blocking('Schedule a maintenance window.'));
        }

        if ($column->inlineUnique) {
            return new Verdict('add-column', Classification::inplaceRebuild(),
                'Adding a column with an inline UNIQUE index rebuilds the table in place; writes continue.',
                "Add `{$column->name}` without ->unique() (instant), then add `\$table->unique('{$column->name}')` in a separate statement, which builds online. ".Advice::OFF_PEAK);
        }

        if ($column->isGenerated()) {
            return $server->supportsInstant()
                ? new Verdict('add-column', Classification::instant(), 'Adding a VIRTUAL generated column is instant (metadata only).')
                : new Verdict('add-column', Classification::inplaceMetadata(), 'Adding a VIRTUAL generated column only changes metadata.');
        }

        $reason = null;
        if (! $server->supportsInstant()) {
            $reason = "MySQL {$server->version} has no INSTANT ADD COLUMN (needs 8.0.12+)";
        } elseif ($table !== null && $table->isCompressed()) {
            $reason = "`{$table->name}` uses ROW_FORMAT=COMPRESSED, which can't add columns instantly";
        } elseif ($op->position !== null && ! $server->supportsInstantAnyPosition()) {
            $where = $op->position === 'FIRST' ? 'FIRST' : "AFTER `{$op->after}`";
            $reason = "MySQL {$server->version} only adds columns instantly at the end of the table, not {$where} (any position needs 8.0.29+)";
        }

        if ($reason !== null) {
            $suggestion = $op->position !== null && $server->supportsInstant()
                ? 'Drop the ->after()/->first() so the column is appended at the end; that is instant on MySQL 8.0.12+.'
                : self::online($context);

            return new Verdict('add-column', Classification::inplaceRebuild(),
                "Rebuilds the whole table in place: {$reason}. Writes continue during the rebuild.",
                $suggestion);
        }

        return new Verdict('add-column', Classification::instant(rebuildIfNotInstant: true),
            'Adds the column instantly (metadata only).');
    }

    private function drop(DropColumn $op, RuleContext $context): Verdict
    {
        $server = $context->server;
        $table = $context->table;
        $suggestion = Advice::dropColumn($op->column);

        $reason = null;
        if (! $server->supportsInstantAnyPosition()) {
            $reason = "MySQL {$server->version} has no INSTANT DROP COLUMN (needs 8.0.29+)";
        } elseif ($table !== null && $table->isColumnIndexed($op->column)) {
            $reason = "`{$op->column}` is part of an index, which can't be dropped instantly";
        } elseif ($table !== null && $table->isCompressed()) {
            $reason = "`{$table->name}` uses ROW_FORMAT=COMPRESSED";
        }

        if ($reason !== null) {
            return new Verdict('drop-column', Classification::inplaceRebuild(),
                "Rebuilds the whole table in place: {$reason}. Writes continue during the rebuild.",
                $suggestion, destructive: true);
        }

        return new Verdict('drop-column', Classification::instant(rebuildIfNotInstant: true),
            'Drops the column instantly (metadata only).', $suggestion, destructive: true);
    }

    private function rename(RenameColumn $op, RuleContext $context): Verdict
    {
        $suggestion = Advice::renameColumn($op->from, $op->to);

        return $context->server->supportsInstantRenameColumn()
            ? new Verdict('rename-column', Classification::instant(), 'Renames the column instantly (metadata only).', $suggestion, destructive: true)
            : new Verdict('rename-column', Classification::inplaceMetadata(), 'Renames the column in place (metadata only).', $suggestion, destructive: true);
    }

    private function modify(ModifyColumn $op, RuleContext $context): Verdict
    {
        $table = $context->table;
        $old = $table?->column($op->from);

        if ($table === null || $old === null) {
            $where = $table === null ? "table `{$op->table}`" : "column `{$op->from}`";

            return new Verdict('modify-column', null,
                "Can't tell what this MODIFY changes: {$where} is not in the schema snapshot. Changing a column's type or length usually copies the table and blocks writes.",
                'Provide the current schema (SHOW CREATE TABLE) so Preflight can compare definitions.',
                fixedSeverity: Severity::Warn);
        }

        $moves = $op->position !== null && $this->moves($op, $table);
        $diff = new ColumnDiff($old, $op->column, $table, $context->server, $moves);
        $classification = $diff->classification;
        $changes = $diff->changes === [] ? 'no effective change' : implode('; ', $diff->changes);

        if ($classification->algorithm === Algorithm::Copy) {
            return new Verdict('modify-column', $classification,
                "Changes `{$op->from}` ({$changes}). This forces ALGORITHM=COPY: the table is copied row by row and writes are blocked until it finishes.",
                Advice::expandContract($table->name, $op->from), destructive: $op->renames());
        }

        if ($classification->rebuildsTable()) {
            return new Verdict('modify-column', $classification,
                "Changes `{$op->from}` ({$changes}). The {$diff->reason} rebuilds the whole table in place; writes continue.",
                self::online($context), destructive: $op->renames());
        }

        return new Verdict('modify-column', $classification,
            "Changes `{$op->from}` ({$changes}): metadata only.",
            destructive: $op->renames());
    }

    /**
     * Advice for work MySQL does online (INPLACE, LOCK=NONE). When the statement is just this one clause, show
     * how to pin the algorithm so it can never fall back to a locking copy.
     */
    public static function online(RuleContext $context): string
    {
        return count($context->statement->operations) === 1
            ? Advice::pinOnline($context->statement->sql)
            : Advice::OFF_PEAK;
    }

    private function moves(ModifyColumn $op, Table $table): bool
    {
        $current = $table->columnPosition($op->from);
        if ($current === null) {
            return true;
        }
        if ($op->position === 'FIRST') {
            return $current !== 0;
        }
        $after = $op->after !== null ? $table->columnPosition($op->after) : null;

        return $after === null || $after + 1 !== $current;
    }

    private function alterColumn(AlterColumn $op, RuleContext $context): Verdict
    {
        $what = match ($op->action) {
            AlterColumn::SET_DEFAULT => 'Sets the default',
            AlterColumn::DROP_DEFAULT => 'Drops the default',
            default => 'Changes column visibility',
        };

        return $context->server->supportsInstant()
            ? new Verdict('alter-column', Classification::instant(), "{$what} of `{$op->column}` instantly (metadata only).")
            : new Verdict('alter-column', Classification::inplaceMetadata(), "{$what} of `{$op->column}` (metadata only).");
    }
}
