<?php

declare(strict_types=1);

namespace Preflight\Core\Rules;

/**
 * Safer alternatives, written for Laravel and filled in with the real table and column names, so the
 * reader can act on them directly. Code is in backticks (rendered as code in PR comments and the dashboard).
 */
final class Advice
{
    public const ONLINE_SCHEMA_CHANGE = 'run it with gh-ost or pt-online-schema-change, which copy the table in the background without blocking writes';

    public const OFF_PEAK = 'Writes continue, but expect heavy I/O and replica lag on a large table; run it off-peak.';

    public static function blocking(string $what): string
    {
        return "{$what} Or ".self::ONLINE_SCHEMA_CHANGE.'.';
    }

    /**
     * A type change that copies the table: change it in steps instead.
     */
    public static function expandContract(string $table, string $column): string
    {
        $new = $column.'_new';

        return "Expand and contract instead: (1) add `{$new}` with the new type as a nullable column (instant); "
            ."(2) make the app write both columns, and backfill old rows in batches from a queued job, e.g. `DB::table('{$table}')->whereNull('{$new}')->chunkById(1000, …)`; "
            ."(3) switch reads to `{$new}`; (4) in a later deploy, drop `{$column}` and rename `{$new}` (both instant on MySQL 8.0.29+). "
            .'Or '.self::ONLINE_SCHEMA_CHANGE.'.';
    }

    /**
     * Foreign keys added with foreign_key_checks on copy the table; off, MySQL only records them.
     *
     * @param  list<string>  $columns
     */
    public static function foreignKeyWithoutChecks(string $table, array $columns, ?string $references): string
    {
        $column = $columns[0] ?? 'column';
        $parent = $references ?? 'parent';
        $foreign = count($columns) === 1 ? "'{$column}'" : "['".implode("', '", $columns)."']";

        return 'Add it with foreign key checks off, so MySQL only records the constraint (in place, no copy): '
            ."`Schema::withoutForeignKeyConstraints(fn () => Schema::table('{$table}', fn (Blueprint \$table) => \$table->foreign({$foreign})->references('id')->on('{$parent}')));`. "
            .'Existing rows are then not validated, so check for orphans first: '
            ."`SELECT COUNT(*) FROM {$table} c LEFT JOIN {$parent} p ON p.id = c.{$column} WHERE c.{$column} IS NOT NULL AND p.id IS NULL` "
            .'(adjust `id` if the key references another column).';
    }

    /**
     * Online work that should stay online: pin the algorithm so MySQL errors instead of silently locking.
     */
    public static function pinOnline(string $sql, string $algorithm = 'INPLACE'): string
    {
        // Identifier backticks would break the surrounding `code` span; Laravel's names don't need them.
        $statement = str_replace('`', '', rtrim(trim((string) preg_replace('/\s+/', ' ', $sql)), ';'));

        return self::OFF_PEAK.' To guarantee it never falls back to a locking copy, run it as '
            ."`DB::statement(\"{$statement}, ALGORITHM={$algorithm}, LOCK=NONE\")`: MySQL then refuses instead of blocking writes.";
    }

    public static function dropColumn(string $column): string
    {
        return "Ship it in two deploys: first code that no longer uses `{$column}` (remove it from \$fillable, \$casts, queries and API resources), then this migration. "
            .'The data is gone once dropped, so back it up if you may need it.';
    }

    public static function renameColumn(string $from, string $to): string
    {
        return "MySQL renames it at once, but servers still running the old code keep using `{$from}` until the deploy finishes, and their queries fail. "
            ."Safer: add `{$to}`, write to both, backfill, switch reads, then drop `{$from}` in a later deploy. "
            .'Or first deploy code that works with either name.';
    }

    public static function fulltextBlocksAddColumn(string $table, ?string $index, ?string $column): string
    {
        $drop = $index !== null ? "`\$table->dropIndex('{$index}')`" : 'the FULLTEXT index';
        $readd = $column !== null ? "`\$table->fullText('{$column}')`" : 'it';

        return "Split it: drop {$drop}, add the column (now instant), then re-add {$readd}. "
            .'Rebuilding the FULLTEXT index still blocks writes while it runs, so schedule that step for a quiet period, or '
            .self::ONLINE_SCHEMA_CHANGE.'.';
    }

    public static function lockWaitTimeout(): string
    {
        return "Start the migration's up() with `DB::statement('SET SESSION lock_wait_timeout = 5');` so it gives up after 5 seconds instead of stalling the app, and retry the deploy.";
    }

    public static function batchedBackfill(?string $table): string
    {
        $name = $table ?? 'table';

        return "Move the backfill into a queued job that updates in batches, e.g. `DB::table('{$name}')->where(…)->chunkById(1000, fn (\$rows) => …)`, "
            .'so each batch is a short transaction and replicas keep up.';
    }
}
