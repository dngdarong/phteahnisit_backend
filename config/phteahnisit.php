<?php

return [
    /*
    | Business Rules doc: "Maximum upload size should be configurable."
    | Value in kilobytes (Laravel's `max:` image rule uses KB).
    */
    'max_image_kb' => env('MAX_ROOM_IMAGE_KB', 5120), // 5MB default

    /*
    | Phase 1 security hardening.
    | Local/testing environments seed demo data automatically; other
    | environments must opt in explicitly with SEED_DEMO_DATA=true.
    */
    'seed_demo_data' => env('SEED_DEMO_DATA', false),

    'demo' => [
        'default_password' => env('DEMO_DEFAULT_PASSWORD', 'ChangeThisDemoPassword!2026'),
        'admin_name' => env('DEMO_ADMIN_NAME', 'Admin Phteahnisit'),
        'admin_email' => env('DEMO_ADMIN_EMAIL', 'admin@phteahnisit.test'),
        'admin_phone' => env('DEMO_ADMIN_PHONE', '012345678'),
        'super_admin_name' => env('DEMO_SUPER_ADMIN_NAME', 'Super Admin Phteahnisit'),
        'super_admin_email' => env('DEMO_SUPER_ADMIN_EMAIL', 'superadmin@phteahnisit.test'),
        'super_admin_phone' => env('DEMO_SUPER_ADMIN_PHONE', '012345679'),
    ],

    'auth' => [
        'login' => [
            'max_attempts' => (int) env('AUTH_LOGIN_MAX_ATTEMPTS', 5),
            'decay_minutes' => (int) env('AUTH_LOGIN_DECAY_MINUTES', 1),
        ],
        'register' => [
            'max_attempts' => (int) env('AUTH_REGISTER_MAX_ATTEMPTS', 3),
            'decay_minutes' => (int) env('AUTH_REGISTER_DECAY_MINUTES', 1),
        ],
    ],

    /*
    | General-purpose limit applied to the whole API (bootstrap/app.php),
    | on top of - not instead of - the tighter auth-login/auth-register
    | limits above. Previously only login/register were throttled at all;
    | every other endpoint (public room search included) had no request
    | limit whatsoever. Keyed by user id when authenticated, IP otherwise
    | (see RateLimiter::for('api', ...) in AppServiceProvider).
    */
    'api' => [
        'max_attempts' => (int) env('API_RATE_LIMIT_MAX_ATTEMPTS', 60),
        'decay_minutes' => (int) env('API_RATE_LIMIT_DECAY_MINUTES', 1),
    ],

    'security_headers' => [
        'x_frame_options' => env('SECURITY_X_FRAME_OPTIONS', 'DENY'),
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'content_security_policy' => env(
            'SECURITY_CONTENT_SECURITY_POLICY',
            "default-src 'self'; frame-ancestors 'self'; object-src 'none'; base-uri 'self'"
        ),
        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
    ],
];
