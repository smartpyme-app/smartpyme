<?php

$allowedOriginsEnv = env('CORS_ALLOWED_ORIGINS');
if ($allowedOriginsEnv === null || $allowedOriginsEnv === '' || trim((string) $allowedOriginsEnv) === '*') {
    $allowedOrigins = ['*'];
} else {
    $allowedOrigins = array_values(
        array_filter(
            array_map('trim', explode(',', (string) $allowedOriginsEnv)),
            static fn (string $o): bool => $o !== ''
        )
    );
    if ($allowedOrigins === []) {
        $allowedOrigins = ['*'];
    }
}

$supportsCredentials = filter_var(
    env('CORS_SUPPORTS_CREDENTIALS', false),
    FILTER_VALIDATE_BOOLEAN
);

if ($supportsCredentials) {
    $allowedOrigins = array_values(
        array_filter($allowedOrigins, static fn (string $o): bool => $o !== '*')
    );
    if ($allowedOrigins === []) {
        $allowedOrigins = array_values(
            array_filter(
                array_map('trim', explode(
                    ',',
                    (string) (env('CORS_ALLOWED_ORIGINS') ?: 'https://app-unificado.smartpyme.site,http://localhost:4200')
                )),
                static fn (string $o): bool => $o !== ''
            )
        );
    }
    if ($allowedOrigins === []) {
        $allowedOrigins = ['https://app-unificado.smartpyme.site', 'http://localhost:4200'];
    }
}

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['*', 'api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => $supportsCredentials,

];
