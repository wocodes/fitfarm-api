<?php

$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))
)));

$configuredPatterns = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))
)));

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Auth uses bearer tokens (Sanctum), so credentials are never exposed.
    | Custom gym domains can be added to CORS_ALLOWED_ORIGINS (exact origins)
    | or matched with CORS_ALLOWED_ORIGIN_PATTERNS (comma-separated regexes).
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins === ['*'] ? ['*'] : $allowedOrigins,

    'allowed_origins_patterns' => array_merge([
        // Platform apex and tenant/admin subdomains.
        '/^https:\/\/([\w-]+\.)?fitfarmhq\.com$/',
        // Local development
        '/^http:\/\/(localhost|127\.0\.0\.1):3000$/',
        '/^http:\/\/(localhost|127\.0\.0\.1):3001$/',
        '/^http:\/\/(localhost|127\.0\.0\.1):3002$/',
    ], $configuredPatterns),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
