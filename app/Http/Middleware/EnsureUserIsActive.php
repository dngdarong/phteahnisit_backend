<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            $token = $user->currentAccessToken();
            $user->currentAccessToken()?->delete();
            Log::channel('security')->warning('Inactive account token revoked.', [
                'event' => 'auth.token.revoked',
                'user_id' => $user->id,
                'token_id' => $token?->id,
                'token_name' => $token?->name,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'reason' => 'inactive_account',
            ]);

            return response()->json([
                'message' => 'Your account has been disabled. Contact support.',
            ], 403);
        }

        return $next($request);
    }
}
