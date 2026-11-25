<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admins approve people on the beta waitlist (or take access away).
 */
class BetaController extends Controller
{
    public function index(): Response
    {
        $people = User::query()
            ->orderByRaw('approved_at is null desc')
            ->orderBy('created_at')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'login' => $user->github_login,
                'name' => $user->name,
                'avatar' => $user->avatar_url,
                'teamSize' => $user->team_size,
                'note' => $user->waitlist_note,
                'joinedAt' => $user->created_at?->toIso8601String(),
                'approvedAt' => $user->approved_at?->toIso8601String(),
                'admin' => $user->isAdmin(),
            ]);

        return Inertia::render('admin/beta', [
            'people' => $people,
            'betaEnabled' => (bool) config('preflight.beta'),
        ]);
    }

    public function approve(User $user): RedirectResponse
    {
        $user->forceFill(['approved_at' => now()])->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => "@{$user->github_login} can now use Preflight."]);

        return back();
    }

    public function revoke(User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Admins always have access.']);

            return back();
        }

        $user->forceFill(['approved_at' => null])->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => "@{$user->github_login} is back on the waitlist."]);

        return back();
    }
}
