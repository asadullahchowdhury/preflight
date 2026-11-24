<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryController extends Controller
{
    public function show(Request $request, Repository $repository): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->repositories()->whereKey($repository->id)->exists(), 404);

        $repository->loadCount('analyses')->load(['latestAnalysis', 'installation']);
        $analyses = $repository->analyses()->latest()->paginate(15)
            ->through(fn (Analysis $a) => AnalysisController::summary($a->setRelation('repository', $repository)));

        return Inertia::render('repositories/show', [
            'repository' => self::summary($repository),
            'analyses' => $analyses,
            'audience' => (string) config('preflight.oidc.audience'),
            'apiUrl' => url('/api/analyses'),
        ]);
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
