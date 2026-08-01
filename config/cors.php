<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | phteahnisit-frontend (Vite, http://localhost:5173) calls this API from
    | a different origin than the backend (http://localhost:8000). Without
    | this, every fetch/axios call from the SPA is blocked by the browser.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', env(
        'FRONTEND_URLS',
        'http://localhost:5173,http://127.0.0.1:5173'
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
