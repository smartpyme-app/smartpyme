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
    ],

];
