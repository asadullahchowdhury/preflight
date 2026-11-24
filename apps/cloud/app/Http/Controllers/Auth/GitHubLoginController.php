<?php

namespace App\Http\Controllers\Auth;

use App\GitHub\InstallationSync;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as OAuthTwoUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * "Sign in with GitHub", through the GitHub App's OAuth client. The user's token lets us confirm which
 * installations they can see.
 */
class GitHubLoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('auth/login', [
            'configured' => filled(config('services.github.client_id')),
            'devLogin' => app()->isLocal() && config('preflight.dev_login'),
        ]);
    }

    public function redirect(): SymfonyRedirect|RedirectResponse
    {
        if (blank(config('services.github.client_id'))) {
            return to_route('login')->with('error', 'GitHub sign-in is not configured yet.');
        }

        return Socialite::driver('github')->redirect();
    }

    public function callback(Request $request, InstallationSync $sync): RedirectResponse
    {
        if ($request->has('error')) {
            return to_route('login');
        }

        try {
            $github = Socialite::driver('github')->user();
        } catch (Throwable $e) {
            Log::warning('GitHub sign-in failed', ['error' => $e->getMessage()]);

            return to_route('login');
        }

        $user = User::query()->updateOrCreate(
            ['github_id' => (int) $github->getId()],
            [
                'github_login' => (string) $github->getNickname(),
                'name' => (string) ($github->getName() ?: $github->getNickname()),
                'email' => $github->getEmail(),
                'avatar_url' => $github->getAvatar(),
                'github_token' => $github instanceof OAuthTwoUser ? $github->token : null,
            ],
        );

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        try {
            $sync->forUser($user);
        } catch (Throwable $e) {
            Log::warning('Could not sync installations after sign-in', ['user' => $user->id, 'error' => $e->getMessage()]);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
