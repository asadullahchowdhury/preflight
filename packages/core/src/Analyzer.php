<?php

declare(strict_types=1);

namespace Preflight\Core;

use Preflight\Core\Estimate\DurationEstimator;
use Preflight\Core\Estimate\Thresholds;
use Preflight\Core\Operations\CreateTable;
use Preflight\Core\Operations\Operation;
use Preflight\Core\Operations\RenameTable;
use Preflight\Core\Operations\SetVariable;
use Preflight\Core\Report\Advisory;
use Preflight\Core\Report\Algorithm;
use Preflight\Core\Report\Classification;
use Preflight\Core\Report\Cost;
use Preflight\Core\Report\Finding;
use Preflight\Core\Report\LockLevel;
use Preflight\Core\Report\Report;
use Preflight\Core\Report\Severity;
use Preflight\Core\Report\StatementResult;
use Preflight\Core\Rules\ColumnRules;
use Preflight\Core\Rules\ConstraintRules;
use Preflight\Core\Rules\IndexRules;
use Preflight\Core\Rules\Rule;
use Preflight\Core\Rules\RuleContext;
use Preflight\Core\Rules\StatementRules;
use Preflight\Core\Rules\TableRules;
use Preflight\Core\Rules\Verdict;
use Preflight\Core\Schema\SchemaMutator;
use Preflight\Core\Schema\SchemaSnapshot;
use Preflight\Core\Server\ServerProfile;
use Preflight\Core\Sql\ParsedStatement;
use Preflight\Core\Sql\StatementParser;
use Preflight\Core\Stats\StatsSet;
use Preflight\Core\Stats\TableStats;

/**
 * Entry point: SQL in, deterministic verdicts out. No database access, no AI.
 */
final class Analyzer
{
    /** Laravel's bookkeeping table; inserts into it are not part of the user's migration. */
    private const BOOKKEEPING_TABLES = ['migrations'];

    /** @var list<Rule> */
    private readonly array $rules;

    public function __construct(
        private readonly StatementParser $parser = new StatementParser,
        private readonly DurationEstimator $estimator = new DurationEstimator,
        private readonly Thresholds $thresholds = new Thresholds,
        private readonly SchemaMutator $mutator = new SchemaMutator,
    ) {
        $this->rules = [new ColumnRules, new IndexRules, new ConstraintRules, new TableRules, new StatementRules];
    }

    /**
     * @param  string|list<string>  $sql  One script, or statements as captured by `migrate --pretend`.
     * @param  ?SchemaSnapshot  $schema  Tables as they exist before the migration (SHOW CREATE TABLE).
     * @param  ?StatsSet  $stats  Production table sizes; enables duration estimates and size-aware severity.
     */
    public function analyze(string|array $sql, ServerProfile $server, ?SchemaSnapshot $schema = null, ?StatsSet $stats = null): Report
    {
        $script = is_array($sql) ? implode(";\n", array_map(static fn (string $s) => rtrim(trim($s), ';'), $sql)) : $sql;
        $working = $schema !== null ? clone $schema : new SchemaSnapshot;
        $snapshotProvided = $schema !== null && $schema->tables() !== [];

        /** @var array<string, true> $newTables */
        $newTables = [];
        /** @var array<string, array{name: string, rebuilds: int}> $touched existing tables altered by online DDL */
        $touched = [];
        $results = [];
        $missingTables = [];

        foreach ($this->parser->parse($script) as $index => $statement) {
            if ($statement->kind === ParsedStatement::SET) {
                foreach ($statement->operations as $operation) {
                    if ($operation instanceof SetVariable && $operation->name === 'foreign_key_checks') {
                        $server = $server->withForeignKeyChecks(! in_array(strtoupper($operation->value), ['0', 'OFF', 'FALSE'], true));
                    }
                }

                continue;
            }
            if ($statement->kind === ParsedStatement::IGNORED || $this->isBookkeeping($statement)) {
                continue;
            }

            $tableKey = $statement->table !== null ? strtolower($statement->table) : null;
            $onNewTable = $tableKey !== null && isset($newTables[$tableKey]) && $statement->kind !== ParsedStatement::CREATE_TABLE;
            $tableBefore = $statement->table !== null ? $working->table($statement->table) : null;
            $context = new RuleContext($server, $statement, $tableBefore);
            $tableStats = $onNewTable ? null : $stats?->for($statement->table);

            $findings = [];
            $classifications = [];
            foreach ($statement->operations as $operation) {
                $verdict = $this->ruleFor($operation)->evaluate($operation, $context);
                if ($verdict->classification !== null) {
                    $classifications[] = $verdict->classification;
                }
                $findings[] = $this->finding($operation->label(), $operation->table, $verdict, $onNewTable, $tableStats);
            }

            $effective = $statement->isOnlineDdl() ? Classification::combine($classifications) : null;

            $advisories = [];
            if ($effective !== null && ! $onNewTable) {
                array_push($advisories, ...$this->statementAdvisories($statement, $effective, $classifications));
            }
            if ($snapshotProvided && $tableBefore === null && ! $onNewTable && $statement->isOnlineDdl() && $tableKey !== null && ! isset($missingTables[$tableKey])) {
                $missingTables[$tableKey] = true;
                $advisories[] = new Advisory('table-not-in-snapshot', Severity::Info,
                    "`{$statement->table}` is not in the schema snapshot, so table-specific checks (FULLTEXT indexes, row format, current column definitions) were skipped.",
                    $statement->table);
            }

            $estimate = $effective !== null && $tableStats !== null ? $this->estimator->estimate($effective, $tableStats) : null;

            $results[] = new StatementResult($index, $statement->sql, $statement->table, $findings, $effective, $advisories, $estimate, $onNewTable);

            if ($statement->isOnlineDdl() && ! $onNewTable && $tableKey !== null && $statement->table !== null) {
                $touched[$tableKey] ??= ['name' => $statement->table, 'rebuilds' => 0];
                if ($effective?->rebuildsTable()) {
                    $touched[$tableKey]['rebuilds']++;
                }
            }

            foreach ($statement->operations as $operation) {
                if ($operation instanceof CreateTable && $operation->table !== null) {
                    $newTables[strtolower($operation->table)] = true;
                }
                if ($operation instanceof RenameTable && isset($newTables[strtolower((string) $operation->table)])) {
                    $newTables[strtolower($operation->to)] = true;
                }
                $this->mutator->apply($working, $operation);
            }
        }

        return new Report($server->version, $stats !== null, $results, $this->reportAdvisories($touched));
    }

    private function ruleFor(Operation $operation): Rule
    {
        foreach ($this->rules as $rule) {
            if ($rule->supports($operation)) {
                return $rule;
            }
        }

        throw new \LogicException('No rule for '.$operation::class);
    }

    private function isBookkeeping(ParsedStatement $statement): bool
    {
        return $statement->kind === ParsedStatement::DML
            && $statement->table !== null
            && in_array(strtolower($statement->table), self::BOOKKEEPING_TABLES, true);
    }

    private function finding(string $label, ?string $table, Verdict $verdict, bool $onNewTable, ?TableStats $stats): Finding
    {
        $classification = $verdict->classification;
        $message = $verdict->message;
        $suggestion = $verdict->suggestion;

        if ($onNewTable && ! in_array($verdict->ruleId, ['unknown-statement', 'drop-table', 'truncate-table'], true)) {
            return new Finding($verdict->ruleId, $table, $label, $classification, Severity::Info,
                $message.' The table is created earlier in this migration batch, so it is empty and this is instant in practice.');
        }

        if ($verdict->fixedSeverity !== null) {
            $severity = $verdict->fixedSeverity;
        } elseif ($classification === null) {
            $severity = Severity::Warn;
        } elseif ($stats !== null) {
            $estimate = $this->estimator->estimate($classification, $stats);
            $severity = $this->sizeAwareSeverity($classification, $estimate->maxSeconds);
            $message .= ' Estimated '.$estimate->format().' on this table ('.$estimate->basis.').';
        } else {
            $severity = $this->staticSeverity($classification);
        }

        if ($verdict->destructive) {
            $severity = $severity->max(Severity::Warn);
        }

        return new Finding($verdict->ruleId, $table, $label, $classification, $severity, $message, $suggestion);
    }

    private function staticSeverity(Classification $classification): Severity
    {
        if ($classification->blocksWrites()) {
            return Severity::Danger;
        }

        return $classification->cost === Cost::Metadata ? Severity::Info : Severity::Warn;
    }

    private function sizeAwareSeverity(Classification $classification, float $maxSeconds): Severity
    {
        if ($classification->blocksWrites()) {
            return match (true) {
                $maxSeconds >= $this->thresholds->blockingDangerSeconds => Severity::Danger,
                $maxSeconds >= $this->thresholds->blockingWarnSeconds => Severity::Warn,
                default => Severity::Info,
            };
        }

        if ($classification->cost !== Cost::Metadata && $maxSeconds >= $this->thresholds->longRunningWarnSeconds) {
            return Severity::Warn;
        }

        return Severity::Info;
    }

    /**
     * @param  list<Classification>  $parts
     * @return list<Advisory>
     */
    private function statementAdvisories(ParsedStatement $statement, Classification $effective, array $parts): array
    {
        $advisories = [];
        $table = $statement->table;

        // An INSTANT clause dragged into a rebuild by another clause in the same statement.
        if ($effective->algorithm !== Algorithm::Instant && count($parts) > 1) {
            foreach ($parts as $part) {
                if ($part->rebuildIfNotInstant) {
                    $advisories[] = new Advisory('combined-rebuild', Severity::Warn,
                        'This statement mixes an instant column change with an operation that needs '.$effective->algorithm->value.', so the whole statement rebuilds the table. Split it into separate ALTER TABLE statements.',
                        $table);
                    break;
                }
            }
        }

        // ALGORITHM= / LOCK= written in the migration that MySQL will refuse.
        if ($statement->explicitAlgorithm !== null && $statement->explicitAlgorithm !== 'DEFAULT') {
            $requested = Algorithm::tryFrom($statement->explicitAlgorithm);
            if ($requested !== null && $requested->rank() < $effective->algorithm->rank()) {
                $advisories[] = new Advisory('explicit-algorithm-fails', Severity::Warn,
                    "The statement requests ALGORITHM={$requested->value}, but this change needs {$effective->algorithm->value}; MySQL will reject it with an error and the migration will fail.",
                    $table);
            }
        }
        if ($statement->explicitLock !== null && $statement->explicitLock !== 'DEFAULT') {
            $requested = LockLevel::tryFrom($statement->explicitLock);
            if ($requested !== null && $requested->rank() < $effective->lock->rank()) {
                $advisories[] = new Advisory('explicit-lock-fails', Severity::Warn,
                    "The statement requests LOCK={$requested->value}, but this change needs LOCK={$effective->lock->value}; MySQL will reject it and the migration will fail.",
                    $table);
            }
        }

        return $advisories;
    }

    /**
     * @param  array<string, array{name: string, rebuilds: int}>  $touched
     * @return list<Advisory>
     */
    private function reportAdvisories(array $touched): array
    {
        $advisories = [];

        foreach ($touched as $table) {
            if ($table['rebuilds'] > 1) {
                $advisories[] = new Advisory('multiple-rebuilds', Severity::Warn,
                    "`{$table['name']}` is rebuilt {$table['rebuilds']} times by separate statements. Combine the changes into one ALTER TABLE so the table is rebuilt once.",
                    $table['name']);
            }
        }

        foreach ($touched as $table) {
            $advisories[] = new Advisory('mdl-wait', Severity::Info,
                "Every ALTER on `{$table['name']}`, even an instant one, needs a brief exclusive metadata lock. It waits behind any open transaction on the table, and all new queries queue behind it. Set a short lock_wait_timeout (e.g. 5s) for the migration session and retry on timeout.",
                $table['name']);
        }

        return $advisories;
    }
}
