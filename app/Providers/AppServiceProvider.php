<?php

namespace App\Providers;

use App\Support\AuthRateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth-login', function (Request $request) {
            $limit = max(1, (int) config('phteahnisit.auth.login.max_attempts', 5));
            $decayMinutes = max(1, (int) config('phteahnisit.auth.login.decay_minutes', 1));

            return Limit::perMinutes($decayMinutes, $limit)
                ->by(AuthRateLimiter::loginKey($request))
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('auth-register', function (Request $request) {
            $limit = max(1, (int) config('phteahnisit.auth.register.max_attempts', 3));
            $decayMinutes = max(1, (int) config('phteahnisit.auth.register.decay_minutes', 1));

            return Limit::perMinutes($decayMinutes, $limit)
                ->by(AuthRateLimiter::registerKey($request))
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many registration attempts. Please try again later.',
                    ], 429, $headers);
                });
        });
    }
}
