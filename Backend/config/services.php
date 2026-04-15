<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_ID'),
        'client_secret' => env('GOOGLE_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT')
    ],

    'facebook' => [
        'client_id' =>      env('FACEBOOK_ID'),
        'client_secret' =>  env('FACEBOOK_SECRET'),
        'redirect' =>       env('FACEBOOK_REDIRECT')
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET')
    ],

    'payment' => [
        'default_gateway' => env('PAYMENT_GATEWAY', 'n1co'),
        'sandbox_mode' => env('PAYMENT_SANDBOX_MODE', true),
    ],

    'nico' => [
        'api_key' => env('NICO_API_KEY'),
        'client_id' => env('NICO_CLIENT_ID'),
        'client_secret' => env('NICO_CLIENT_SECRET'),
        'sandbox_api_key' => env('NICO_SANDBOX_API_KEY'),
        'base_url' => env('NICO_BASE_URL', 'https://api.n1co.com/api/v2'),
        'sandbox_url' => env('NICO_SANDBOX_URL', 'https://api-sandbox.n1co.shop/api/v2'),
        'webhook_secret' => env('NICO_WEBHOOK_SECRET'),
        'sandbox_mode' => env('APP_ENV') !== 'production',
    ],

    'bedrock' => [
        'key' => config('bedrock.key'),
        'secret' => config('bedrock.secret'),
        'region' => config('bedrock.region'),
    ],
    'whatsapp' => [
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'smartpyme_verify_token'),
        'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v21.0/131106576743413/messages'),
        'webhook_url' => env('WHATSAPP_WEBHOOK_URL', '/api/whatsapp/webhook'),
        'dev_mode' => env('WHATSAPP_DEV_MODE', true),
        'use_ai' => env('WHATSAPP_USE_AI', false), // 
        'use_whatsapp_business' => env('WHATSAPP_USE_BUSINESS', true), //
    ],
    'shopify' => [
        'enabled' => env('SHOPIFY_ENABLED', false),
        'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),
    ],

    /*
    | Ruta al ejecutable openssl.exe (Windows). FE Costa Rica (dgt-cr-signer) invoca el CLI.
    | Ej.: C:\Program Files\OpenSSL-Win64\bin\openssl.exe — si null, se buscan rutas típicas.
    */
    'openssl_bin' => env('OPENSSL_BIN'),

    /*
    | API pública Hacienda CR (catálogo CABYS, contribuyente, exoneraciones, tipo de cambio).
    | Límites: ver https://api.hacienda.go.cr/docs/ — usar caché y evitar ráfagas.
    */
    'hacienda_cr' => [
        'base_url' => env('HACIENDA_CR_API_BASE_URL', 'https://api.hacienda.go.cr'),
        'timeout_seconds' => (int) env('HACIENDA_CR_HTTP_TIMEOUT', 25),
        // Hacienda a veces bloquea User-Agent genéricos; un navegador reciente reduce rechazos WAF.
        'user_agent' => env(
            'HACIENDA_CR_USER_AGENT',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 SmartPyme-FE-CR/1'
        ),
        'cache' => [
            'cabys_codigo_seconds' => (int) env('HACIENDA_CR_CACHE_CABYS_CODIGO', 21600),
            'cabys_query_seconds' => (int) env('HACIENDA_CR_CACHE_CABYS_Q', 3600),
            'contribuyente_seconds' => (int) env('HACIENDA_CR_CACHE_CONTRIBUYENTE', 43200),
            'exoneracion_seconds' => (int) env('HACIENDA_CR_CACHE_EXONERACION', 7200),
            'tipo_cambio_seconds' => (int) env('HACIENDA_CR_CACHE_TC_DOLAR', 1800),
        ],
    ],

];
