<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-checks user status on every authenticated request, so a token
 * issued before an admin deactivates the account stops working
 * immediately rather than staying valid until it expires.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Your account has been disabled. Contact support.',
            ], 403);
        }

        return $next($request);
    }
}
