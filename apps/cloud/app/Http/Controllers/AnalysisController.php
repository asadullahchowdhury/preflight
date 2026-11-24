<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class AnalysisController extends Controller
{
    public function show(Request $request, Analysis $analysis): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->repositories()->whereKey($analysis->repository_id)->exists(), 404);

        $analysis->load('repository');
        $report = $analysis->report ?? [];

        return Inertia::render('analyses/show', [
            'analysis' => self::summary($analysis) + [
                'baseRef' => $analysis->base_ref,
                'runUrl' => $analysis->run_url,
                'actor' => $analysis->actor,
                'sizeAware' => $analysis->size_aware,
                'mysqlVersion' => $analysis->mysql_version,
                'status' => $analysis->status,
                'error' => $analysis->error,
            ],
            'repository' => RepositoryController::summary($analysis->repository),
            'statements' => array_values(array_map(static fn ($s) => Arr::only((array) $s, [
                'index', 'migration', 'sql', 'table', 'severity', 'algorithm', 'lock', 'rebuilds_table',
                'blocks_writes', 'on_new_table', 'estimate', 'findings', 'advisories',
            ]), (array) Arr::get($report, 'statements', []))),
            'advisories' => array_values((array) Arr::get($report, 'advisories', [])),
            'notes' => array_values((array) Arr::get($report, 'notes', [])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Analysis $analysis): array
    {
        return [
            'id' => $analysis->id,
            'repository' => $analysis->repository->full_name,
            'repositoryId' => $analysis->repository_id,
            'prNumber' => $analysis->pr_number,
            'prUrl' => $analysis->pullRequestUrl(),
            'headSha' => $analysis->head_sha,
            'severity' => $analysis->severity,
            'statementCount' => $analysis->statement_count,
            'dangerCount' => $analysis->danger_count,
            'warnCount' => $analysis->warn_count,
            'migrations' => $analysis->migrations,
            'createdAt' => $analysis->created_at?->toIso8601String(),
        ];
    }
}
