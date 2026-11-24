<?php

namespace App\Http\Controllers;

use App\GitHub\InstallationSync;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;

class InstallationController extends Controller
{
    /**
     * GitHub's "Setup URL": where people land after installing or reconfiguring the App. The installation id
     * in the query is not trusted; the user's own token decides what they can see.
     */
    public function setup(Request $request, InstallationSync $sync): RedirectResponse
    {
        return $this->sync($request, $sync, $request->query('setup_action') === 'install' ? 'Preflight is installed.' : 'Repositories updated.');
    }

    public function sync(Request $request, InstallationSync $sync, string $message = 'Synced with GitHub.'): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $installations = $sync->forUser($user);
            foreach ($installations as $installation) {
                $sync->repositories($installation);
            }
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
        } catch (Throwable $e) {
            Log::warning('Installation sync failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Could not reach GitHub. Try again in a moment.']);
        }

        return to_route('dashboard');
    }
}
