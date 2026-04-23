<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
     * Con JWT en localStorage (sin cookies de sesión cross-origin), debe ser false.
     * true + allowed_origins * es inválido: el navegador no recibe Access-Control-Allow-Origin.
     * Si necesitas cookies/Sanctum, pon origenes explícitos y supports_credentials true.
     */
    'supports_credentials' => false,

];
