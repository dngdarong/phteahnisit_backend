<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', config('phteahnisit.security_headers.x_frame_options', 'DENY'));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', config('phteahnisit.security_headers.referrer_policy'));
        $response->headers->set('Content-Security-Policy', config('phteahnisit.security_headers.content_security_policy'));

        if ($request->isSecure() && app()->environment('production')) {
            $maxAge = (int) config('phteahnisit.security_headers.hsts_max_age', 31536000);
            $response->headers->set(
                'Strict-Transport-Security',
                sprintf('max-age=%d; includeSubDomains', $maxAge)
            );
        }

        return $response;
    }
}
