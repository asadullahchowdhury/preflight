<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * During the private beta, only approved accounts get past the waitlist.
 */
class EnsureBetaAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->hasAccess()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Your account is on the beta waitlist.'], 403)
                : redirect()->route('waitlist');
        }

        return $next($request);
    }
}
