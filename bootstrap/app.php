<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\AddSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ], append: [
            AddSecurityHeaders::class,
        ]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        /*
         * Every other API error path (401/403 via abort(), 422 via
         * ValidationException, 429 via the rate limiter's ->response())
         * already renders as a plain {message, [errors]} body. Only
         * generic exceptions rendered while APP_DEBUG=true add Laravel's
         * debug keys (exception/file/line/trace), which would leak
         * server file paths. Strip them here so the API contract stays
         * {message, [errors]} regardless of the debug flag - the same
         * "never trust a single layer" reasoning already used for
         * authorize() defense-in-depth elsewhere in this codebase.
         */
        $exceptions->respond(function (Response $response, \Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return $response;
            }

            $data = json_decode($response->getContent(), true);

            if (is_array($data) && array_key_exists('exception', $data)) {
                $response->setContent(json_encode(Arr::only($data, ['message', 'errors'])));
            }

            return $response;
        });
    })->create();
