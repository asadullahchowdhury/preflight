<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where people wait for a private-beta invite, and tell us a little about their team.
 */
class WaitlistController extends Controller
{
    public const TEAM_SIZES = ['just-me', '2-10', '11-50', '51-200', '200+'];

    public function show(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->hasAccess()) {
            return to_route('dashboard');
        }

        return Inertia::render('waitlist', [
            'teamSize' => $user->team_size,
            'note' => $user->waitlist_note,
            'teamSizes' => self::TEAM_SIZES,
            'joinedAt' => $user->created_at?->toIso8601String(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'team_size' => ['nullable', 'string', 'in:'.implode(',', self::TEAM_SIZES)],
            'waitlist_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $user->update($data);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Thanks! That helps us prioritise invites.']);

        return to_route('waitlist');
    }
}
