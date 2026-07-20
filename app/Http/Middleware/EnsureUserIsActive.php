<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only treat an explicit false as deactivated; a missing attribute
        // (e.g. partially hydrated model) must never lock the user out.
        if ($user !== null && $user->is_active === false) {
            if ($request->expectsJson()) {
                $user->currentAccessToken()?->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated.',
                    'data' => null,
                    'meta' => (object) [],
                ], 403);
            }

            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been deactivated.']);
        }

        return $next($request);
    }
}
