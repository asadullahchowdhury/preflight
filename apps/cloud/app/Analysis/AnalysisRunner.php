<?php

namespace App\Analysis;

use App\Models\Analysis;
use App\Models\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Preflight\Core\Analyzer;
use Preflight\Core\Render\MarkdownRenderer;
use Preflight\Core\Render\Sources;
use Preflight\Core\Report\Report;
use Preflight\Core\Report\Severity;
use Preflight\Core\Report\StatementResult;
use Preflight\Core\Schema\SchemaSnapshot;
use Preflight\Core\Server\ServerProfile;
use Preflight\Core\Sql\TokenList;

/**
 * Repeats the analysis CI ran, from the statements and schema it sent, and stores the result.
 *
 * The cloud runs the same engine as `preflight:check`. Running it here (rather than trusting the CI report)
 * is what lets the paid tier add production table sizes later.
 */
class AnalysisRunner
{
    public function __construct(private readonly Analyzer $analyzer = new Analyzer) {}

    /**
     * @param  array<string, mixed>  $payload  validated request body: preflight:check's JSON plus "context"
     * @param  array<string, mixed>  $claims  the verified OIDC claims
     */
    public function run(Repository $repository, array $payload, array $claims): Analysis
    {
        /** @var list<array{sql: string, migration: ?string}> $input */
        $input = Arr::get($payload, 'input.statements', []);

        $statements = [];
        $labels = [];
        foreach ($input as $statement) {
            $count = count(TokenList::statements($statement['sql']));
            for ($i = 0; $i < $count; $i++) {
                $labels[count($labels)] = $statement['migration'] ?? null;
            }
            $statements[] = $statement['sql'];
        }

        // Production's own version (reported with its table sizes) beats whatever CI assumed.
        $version = $repository->stats_server_version ?: (string) Arr::get($payload, 'server_version');
        $server = ServerProfile::mysql(
            $version,
            (bool) Arr::get($payload, 'input.foreign_key_checks', true),
            (string) Arr::get($payload, 'input.default_charset', 'utf8mb4'),
        );
        $schema = SchemaSnapshot::fromSql((string) Arr::get($payload, 'input.schema_sql', ''));
        $stats = $repository->latestStats();

        $report = $this->analyzer->analyze($statements, $server, $schema, $stats);
        $sources = new Sources(array_filter($labels, 'is_string'));

        $notes = array_values(array_filter((array) Arr::get($payload, 'notes', []), 'is_string'));
        if ($stats !== null && $repository->stats_captured_on !== null && $repository->stats_captured_on->lt(now()->subDays(14))) {
            $notes[] = 'Production table sizes are from '.$repository->stats_captured_on->toFormattedDateString().'. Schedule `php artisan preflight:stats --push` daily to keep estimates current.';
        }

        return DB::transaction(fn () => $this->store($repository, $report, $sources, ['notes' => $notes] + $payload, $claims));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $claims
     */
    private function store(Repository $repository, Report $report, Sources $sources, array $payload, array $claims): Analysis
    {
        $counts = ['danger' => 0, 'warn' => 0];
        foreach ($report->statements as $statement) {
            $severity = $statement->severity();
            if ($severity !== Severity::Info) {
                $counts[$severity->value]++;
            }
        }

        $data = $report->toArray();
        $data['statements'] = array_map(fn (StatementResult $s) => ['migration' => $sources->label($s)] + $s->toArray(), $report->statements);
        $data['notes'] = array_values(array_filter((array) Arr::get($payload, 'notes', []), 'is_string'));
        $data['files'] = $this->relativeFiles((array) Arr::get($payload, 'migration_files', []), (string) Arr::get($payload, 'context.workspace', ''));
        $data['markdown'] = (new MarkdownRenderer)->render($report, $sources);

        $analysis = $repository->analyses()->create([
            'pr_number' => $this->pullRequest($claims, $payload),
            'head_sha' => $this->headSha($claims, $payload),
            'base_ref' => Arr::get($claims, 'base_ref') ?: Arr::get($payload, 'context.base_ref'),
            'ref' => Arr::get($claims, 'ref'),
            'actor' => Arr::get($claims, 'actor'),
            'run_url' => $this->runUrl($claims),
            'status' => Analysis::RECEIVED,
            'severity' => $report->severity()->value,
            'mysql_version' => $report->serverVersion,
            'size_aware' => $report->sizeAware,
            'statement_count' => count($report->statements),
            'danger_count' => $counts['danger'],
            'warn_count' => $counts['warn'],
            'migrations' => array_values(array_filter((array) Arr::get($payload, 'migrations', []), 'is_string')),
            'report' => $data,
        ]);

        foreach ($report->statements as $statement) {
            foreach ($statement->findings as $finding) {
                $analysis->findings()->create([
                    'statement_index' => $statement->index,
                    'migration' => $sources->label($statement),
                    'table_name' => $finding->table,
                    'rule' => $finding->ruleId,
                    'severity' => $finding->severity->value,
                    'algorithm' => $finding->algorithm()?->value,
                    'lock_level' => $finding->lock()?->value,
                    'rebuilds_table' => $finding->rebuildsTable(),
                    'blocks_writes' => $finding->blocksWrites(),
                    'operation' => $finding->operation,
                    'message' => $finding->message,
                    'suggestion' => $finding->suggestion,
                ]);
            }
        }

        return $analysis;
    }

    /**
     * The PR number comes from the token when the job ran for a pull request (ref refs/pull/N/merge).
     *
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $payload
     */
    private function pullRequest(array $claims, array $payload): ?int
    {
        if (preg_match('#^refs/pull/(\d+)/#', (string) Arr::get($claims, 'ref'), $m)) {
            return (int) $m[1];
        }
        $number = Arr::get($payload, 'context.pull_request');

        return is_numeric($number) && in_array(Arr::get($claims, 'event_name'), ['pull_request', 'pull_request_target'], true) ? (int) $number : null;
    }

    /**
     * Check runs must be on the PR's head commit to show on the PR; the token carries the merge commit.
     *
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $payload
     */
    private function headSha(array $claims, array $payload): string
    {
        $sha = Arr::get($payload, 'context.head_sha');

        return is_string($sha) && preg_match('/^[0-9a-f]{40}$/', $sha) ? $sha : (string) Arr::get($claims, 'sha');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function runUrl(array $claims): ?string
    {
        $repository = Arr::get($claims, 'repository');
        $run = Arr::get($claims, 'run_id');

        return is_string($repository) && is_scalar($run) ? "https://github.com/{$repository}/actions/runs/{$run}" : null;
    }

    /**
     * Runner paths ("/home/runner/work/app/app/database/…") to repository paths ("database/…").
     *
     * @param  array<mixed>  $files
     * @return array<string, string>
     */
    private function relativeFiles(array $files, string $workspace): array
    {
        $workspace = rtrim(str_replace('\\', '/', $workspace), '/');
        $relative = [];
        foreach ($files as $name => $path) {
            if (! is_string($path)) {
                continue;
            }
            $path = str_replace('\\', '/', $path);
            if ($workspace !== '' && str_starts_with($path, $workspace.'/')) {
                $path = substr($path, strlen($workspace) + 1);
            } elseif (($pos = strpos($path, 'database/migrations/')) !== false) {
                $path = substr($path, $pos);
            }
            $relative[(string) $name] = $path;
        }

        return $relative;
    }
}
