<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\Repository;
use App\Models\TableStat;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryController extends Controller
{
    public function show(Request $request, Repository $repository): Response
    {
        $this->authorizeView($request, $repository);

        $repository->loadCount('analyses')->load(['latestAnalysis', 'installation']);
        $analyses = $repository->analyses()->latest()->paginate(15)
            ->through(fn (Analysis $a) => AnalysisController::summary($a->setRelation('repository', $repository)));

        // The last 40 checks, oldest first, for the history strip.
        $history = $repository->analyses()->latest()->limit(40)->get(['id', 'severity', 'pr_number', 'danger_count', 'warn_count', 'statement_count', 'created_at'])
            ->reverse()->values()
            ->map(fn (Analysis $a) => [
                'id' => $a->id,
                'severity' => $a->severity,
                'prNumber' => $a->pr_number,
                'createdAt' => $a->created_at?->toIso8601String(),
            ]);

        return Inertia::render('repositories/show', [
            'repository' => self::summary($repository),
            'analyses' => $analyses,
            'history' => $history,
            'stats' => $this->stats($repository),
            'newStatsToken' => $request->session()->get('statsToken'),
            'audience' => (string) config('preflight.oidc.audience'),
            'apiUrl' => url('/api/analyses'),
            'cloudUrl' => rtrim((string) config('app.url'), '/'),
        ]);
    }

    public function issueStatsToken(Request $request, Repository $repository): RedirectResponse
    {
        $this->authorizeView($request, $repository);
        $token = $repository->issueStatsToken();

        return back()->with('statsToken', $token);
    }

    public function revokeStatsToken(Request $request, Repository $repository): RedirectResponse
    {
        $this->authorizeView($request, $repository);
        $repository->forceFill(['stats_token_hash' => null, 'stats_token_created_at' => null])->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stats token revoked.']);

        return back();
    }

    private function authorizeView(Request $request, Repository $repository): void
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->repositories()->whereKey($repository->id)->exists(), 404);
    }

    /**
     * The latest production sizes, biggest tables first, each with its last 30 days of total size.
     *
     * @return array<string, mixed>
     */
    private function stats(Repository $repository): array
    {
        $tables = [];
        if ($repository->stats_captured_on !== null) {
            $latest = $repository->tableStats()->whereDate('captured_on', $repository->stats_captured_on)->get()
                ->sortByDesc(fn (TableStat $s) => $s->data_bytes + $s->index_bytes)->take(50);

            $history = $repository->tableStats()
                ->whereIn('table_name', $latest->pluck('table_name'))
                ->whereDate('captured_on', '>=', $repository->stats_captured_on->copy()->subDays(29))
                ->orderBy('captured_on')
                ->get()
                ->groupBy('table_name');

            $tables = $latest->values()->map(fn (TableStat $s) => [
                'name' => $s->table_name,
                'rows' => $s->rows,
                'dataBytes' => $s->data_bytes,
                'indexBytes' => $s->index_bytes,
                'totalBytes' => $s->data_bytes + $s->index_bytes,
                'history' => ($history[$s->table_name] ?? collect())->map(fn (TableStat $h) => [
                    'date' => $h->captured_on->toDateString(),
                    'bytes' => $h->data_bytes + $h->index_bytes,
                ])->values(),
            ]);
        }

        return [
            'hasToken' => $repository->stats_token_hash !== null,
            'tokenCreatedAt' => $repository->stats_token_created_at?->toIso8601String(),
            'pushedAt' => $repository->stats_pushed_at?->toIso8601String(),
            'capturedOn' => $repository->stats_captured_on?->toDateString(),
            'serverVersion' => $repository->stats_server_version,
            'tableCount' => $repository->stats_captured_on !== null
                ? $repository->tableStats()->whereDate('captured_on', $repository->stats_captured_on)->count()
                : 0,
            'tables' => $tables,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Repository $repository): array
    {
        $latest = $repository->latestAnalysis;

        return [
            'id' => $repository->id,
            'fullName' => $repository->full_name,
            'owner' => $repository->owner(),
            'name' => $repository->name(),
            'private' => $repository->private,
            'url' => 'https://github.com/'.$repository->full_name,
            'analysesCount' => $repository->analyses_count ?? null,
            'latest' => $latest !== null ? [
                'id' => $latest->id,
                'severity' => $latest->severity,
                'prNumber' => $latest->pr_number,
                'createdAt' => $latest->created_at?->toIso8601String(),
            ] : null,
        ];
    }
}
