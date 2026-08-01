<?php

namespace App\Http\Middleware;

use App\Enums\RoleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage in routes: ->middleware('role:admin') or ->middleware('role:admin,landlord')
 * Reads from RoleEnum rather than comparing raw strings, so a typo in a
 * route definition fails loudly (ValueError) instead of silently
 * granting/denying access.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowed = array_map(fn (string $r) => RoleEnum::from($r), $roles);

        if (! $user || ! in_array($user->role, $allowed, true)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
