<?php

namespace App\Http\Controllers;

use App\GitHub\GitHubApp;
use App\Models\Analysis;
use App\Models\Installation;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GitHubApp $github): Response
    {
        /** @var User $user */
        $user = $request->user();

        $repositories = $user->repositories()
            ->with(['latestAnalysis', 'installation'])
            ->withCount('analyses')
            ->orderBy('full_name')
            ->get();

        $repositoryIds = $repositories->pluck('id');
        $since = now()->subDays(30);
        $recent = Analysis::query()->whereIn('repository_id', $repositoryIds)->where('created_at', '>=', $since);

        return Inertia::render('dashboard', [
            'installUrl' => $github->installUrl(),
            'installations' => $user->installations()->withCount('repositories')->orderBy('account_login')->get()
                ->map(fn (Installation $i) => [
                    'id' => $i->id,
                    'login' => $i->account_login,
                    'type' => $i->account_type,
                    'avatar' => $i->account_avatar_url,
                    'repositories' => $i->repositories_count,
                    'suspended' => ! $i->isActive(),
                    'settingsUrl' => $i->account_type === 'Organization'
                        ? "https://github.com/organizations/{$i->account_login}/settings/installations/{$i->github_id}"
                        : "https://github.com/settings/installations/{$i->github_id}",
                ]),
            'repositories' => $repositories->map(fn (Repository $r) => RepositoryController::summary($r)),
            'recentAnalyses' => Analysis::query()->whereIn('repository_id', $repositoryIds)
                ->with('repository')->latest()->limit(8)->get()
                ->map(fn (Analysis $a) => AnalysisController::summary($a)),
            'stats' => [
                'repositories' => $repositories->count(),
                'analyses' => (clone $recent)->count(),
                'dangerous' => (clone $recent)->where('severity', 'danger')->count(),
                'statements' => (int) (clone $recent)->sum('statement_count'),
            ],
        ]);
    }
}
