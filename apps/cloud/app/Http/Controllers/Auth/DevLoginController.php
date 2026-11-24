<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Local development only: sign in as the demo user (seeding demo data on first use), to work on the
 * dashboard without a GitHub App. The route exists only when APP_ENV=local and PREFLIGHT_DEV_LOGIN=true.
 */
class DevLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(app()->isLocal() && config('preflight.dev_login'), 404);

        $user = User::query()->where('github_login', DemoSeeder::LOGIN)->first();
        if ($user === null) {
            (new DemoSeeder)->run();
            $user = User::query()->where('github_login', DemoSeeder::LOGIN)->firstOrFail();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
