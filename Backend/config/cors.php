<?php

/**
 * Orígenes por defecto (frontend) cuando CORS_ALLOWED_ORIGINS no está en .env.
 * Incluye producción y Angular en local. Para cualquier origen: CORS_ALLOWED_ORIGINS=*
 */
$defaultOrigins = [
    'https://app-unificado.smartpyme.site',
    'http://localhost:4200',
    'http://127.0.0.1:4200',
];

$allowedOriginsEnv = env('CORS_ALLOWED_ORIGINS');
if ($allowedOriginsEnv === null || $allowedOriginsEnv === '') {
    $allowedOrigins = $defaultOrigins;
} elseif (trim((string) $allowedOriginsEnv) === '*') {
    $allowedOrigins = ['*'];
} else {
    $allowedOrigins = array_values(
        array_filter(
            array_map('trim', explode(',', (string) $allowedOriginsEnv)),
            static fn (string $o): bool => $o !== ''
        )
    );
    if ($allowedOrigins === []) {
        $allowedOrigins = $defaultOrigins;
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
        $allowedOrigins = $defaultOrigins;
    }
}

$useOriginWildcard = in_array('*', $allowedOrigins, true);
/*
 * Con lista explícita, se permiten también orígenes que coincidan (otros subdominios
 * bajo https://*.smartpyme.site, red local, etc.) sin ampliar la lista a mano.
 * No aplica con CORS_ALLOWED_ORIGINS=*.
 */
$allowedOriginsPatterns = $useOriginWildcard
    ? []
    : [
        '#^https://[a-z0-9.-]+\.smartpyme\.site$#i',
        '#^http://(localhost|127\.0\.0\.1|192\.168\.\d{1,3}\.\d{1,3})(:\d+)?$#i',
    ];

return [

    'paths' => ['*', 'api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => $allowedOriginsPatterns,

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => $supportsCredentials,

];
