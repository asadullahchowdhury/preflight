<?php

declare(strict_types=1);

namespace Preflight\Core\Rules;

use Preflight\Core\Operations\ConvertCharset;
use Preflight\Core\Operations\CreateTable;
use Preflight\Core\Operations\DropTable;
use Preflight\Core\Operations\Operation;
use Preflight\Core\Operations\RenameTable;
use Preflight\Core\Operations\TableOption;
use Preflight\Core\Operations\TruncateTable;
use Preflight\Core\Report\Classification;
use Preflight\Core\Report\Severity;
use Preflight\Core\Sql\ParsedStatement;

final class TableRules implements Rule
{
    /** Options that only touch the data dictionary (verified on 8.0.30). */
    private const METADATA_OPTIONS = [
        'COMMENT', 'AUTO_INCREMENT', 'DEFAULT CHARSET', 'COLLATE', 'STATS_PERSISTENT', 'STATS_AUTO_RECALC', 'STATS_SAMPLE_PAGES',
    ];

    /** Options that rebuild the table in place. */
    private const REBUILD_OPTIONS = ['ROW_FORMAT', 'KEY_BLOCK_SIZE', 'FORCE'];

    public function supports(Operation $operation): bool
    {
        return $operation instanceof TableOption
            || $operation instanceof ConvertCharset
            || $operation instanceof RenameTable
            || $operation instanceof CreateTable
            || $operation instanceof DropTable
            || $operation instanceof TruncateTable;
    }

    public function evaluate(Operation $operation, RuleContext $context): Verdict
    {
        $table = (string) $operation->table;

        return match (true) {
            $operation instanceof TableOption => $this->option($operation, $context),
            $operation instanceof ConvertCharset => new Verdict('convert-charset', Classification::copy(),
                "Converting `{$table}` to {$operation->charset} copies the whole table (ALGORITHM=COPY) and blocks writes until it finishes.",
                Advice::blocking('Convert column by column during low traffic, or schedule a maintenance window.')),
            $operation instanceof RenameTable => new Verdict('rename-table',
                $context->statement->kind === ParsedStatement::ALTER ? Classification::instant() : null,
                "Renames `{$table}` to `{$operation->to}` (metadata only).",
                'Code still using the old table name fails as soon as this runs; ship it together with the code change.',
                destructive: true),
            $operation instanceof CreateTable => new Verdict('create-table', null,
                "Creates the new table `{$table}`.", fixedSeverity: Severity::Info),
            $operation instanceof DropTable => new Verdict('drop-table', null,
                "Drops `{$table}` and all of its data. This cannot be undone by rolling back.",
                'Make sure nothing still reads the table and you have a backup; consider renaming it first and dropping it in a later release.',
                fixedSeverity: Severity::Warn),
            $operation instanceof TruncateTable => new Verdict('truncate-table', null,
                "Deletes every row in `{$table}`. This cannot be undone.",
                'Make sure this is intended outside local development.',
                fixedSeverity: Severity::Warn),
            default => throw new \LogicException('Unsupported operation'),
        };
    }

    private function option(TableOption $op, RuleContext $context): Verdict
    {
        $table = (string) $op->table;

        if ($op->option === 'ENGINE') {
            $current = $context->table?->engine;
            if ($current !== null && strcasecmp($current, (string) $op->value) !== 0) {
                return new Verdict('table-engine', Classification::copy(),
                    "Changing `{$table}` from {$current} to {$op->value} copies the whole table and blocks writes.",
                    Advice::blocking('Schedule a maintenance window.'));
            }

            return new Verdict('table-engine', Classification::inplaceRebuild(),
                "ENGINE={$op->value} on an InnoDB table is a \"null rebuild\": the table is rebuilt in place; writes continue.",
                Advice::OFF_PEAK);
        }

        if (in_array($op->option, self::REBUILD_OPTIONS, true)) {
            return new Verdict('table-option', Classification::inplaceRebuild(),
                "{$op->label()} rebuilds `{$table}` in place; writes continue.",
                Advice::OFF_PEAK);
        }

        if (in_array($op->option, self::METADATA_OPTIONS, true)) {
            $note = $op->option === 'DEFAULT CHARSET' || $op->option === 'COLLATE'
                ? ' Existing columns keep their character set; only new columns use the new default.'
                : '';

            return new Verdict('table-option', Classification::inplaceMetadata(),
                "{$op->label()} changes table metadata only.{$note}");
        }

        return new Verdict('table-option', null,
            "Table option {$op->option} is not analysed yet.",
            fixedSeverity: Severity::Warn);
    }
}
