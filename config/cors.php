<?php

return [
    /*
     * Only the API routes need CORS — the SPA itself is served by Laravel on the same origin.
     * Set CORS_ALLOWED_ORIGINS in .env to a comma-separated list of allowed origins.
     * In production, set it to your domain only (e.g. https://your-domain.com).
     * Defaults cover local XAMPP dev + Vite HMR dev server.
     */
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(
        array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost,http://localhost:5173')))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,
];
