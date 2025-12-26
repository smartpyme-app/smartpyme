<?php

use App\Helpers\AwsConfigHelper;

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
        'domain' => AwsConfigHelper::getParameter('/smartpyme/config/services/mailgun/domain') ?: env('MAILGUN_DOMAIN'),
        'secret' => AwsConfigHelper::getSecret('smartpyme/config/services/mailgun', 'secret') ?: env('MAILGUN_SECRET'),
        'endpoint' => AwsConfigHelper::getParameter('/smartpyme/config/services/mailgun/endpoint') ?: env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => AwsConfigHelper::getSecret('smartpyme/config/services/postmark', 'token') ?: env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => AwsConfigHelper::getParameter('/smartpyme/config/services/google/client_id') ?: env('GOOGLE_ID'),
        'client_secret' => AwsConfigHelper::getSecret('smartpyme/config/services/google', 'client_secret') ?: env('GOOGLE_SECRET'),
        'redirect' => AwsConfigHelper::getParameter('/smartpyme/config/services/google/redirect') ?: env('GOOGLE_REDIRECT')
    ],

    'facebook' => [
        'client_id' => AwsConfigHelper::getParameter('/smartpyme/config/services/facebook/client_id') ?: env('FACEBOOK_ID'),
        'client_secret' => AwsConfigHelper::getSecret('smartpyme/config/services/facebook', 'client_secret') ?: env('FACEBOOK_SECRET'),
        'redirect' => AwsConfigHelper::getParameter('/smartpyme/config/services/facebook/redirect') ?: env('FACEBOOK_REDIRECT')
    ],

    'stripe' => [
        'key' => AwsConfigHelper::getSecret('smartpyme/config/services/stripe', 'key') ?: env('STRIPE_KEY'),
        'secret' => AwsConfigHelper::getSecret('smartpyme/config/services/stripe', 'secret') ?: env('STRIPE_SECRET')
    ],

    'payment' => [
        'default_gateway' => AwsConfigHelper::getParameter('/smartpyme/config/services/payment/default_gateway') ?: env('PAYMENT_GATEWAY', 'n1co'),
        'sandbox_mode' => AwsConfigHelper::getParameter('/smartpyme/config/services/payment/sandbox_mode') ?: env('PAYMENT_SANDBOX_MODE', true),
    ],

    'nico' => [
        'api_key' => AwsConfigHelper::getSecret('smartpyme/config/services/nico', 'api_key') ?: env('NICO_API_KEY'),
        'client_id' => AwsConfigHelper::getParameter('/smartpyme/config/services/nico/client_id') ?: env('NICO_CLIENT_ID'),
        'client_secret' => AwsConfigHelper::getSecret('smartpyme/config/services/nico', 'client_secret') ?: env('NICO_CLIENT_SECRET'),
        'sandbox_api_key' => AwsConfigHelper::getSecret('smartpyme/config/services/nico', 'sandbox_api_key') ?: env('NICO_SANDBOX_API_KEY'),
        'base_url' => AwsConfigHelper::getParameter('/smartpyme/config/services/nico/base_url') ?: env('NICO_BASE_URL', 'https://api.n1co.com/api/v2'),
        'sandbox_url' => AwsConfigHelper::getParameter('/smartpyme/config/services/nico/sandbox_url') ?: env('NICO_SANDBOX_URL', 'https://api-sandbox.n1co.shop/api/v2'),
        'webhook_secret' => AwsConfigHelper::getSecret('smartpyme/config/services/nico', 'webhook_secret') ?: env('NICO_WEBHOOK_SECRET'),
        'sandbox_mode' => env('APP_ENV') !== 'production',
    ],

    'bedrock' => [
        'key' => AwsConfigHelper::getSecret('smartpyme/config/services/bedrock', 'key') ?: env('BEDROCK_KEY'),
        'secret' => AwsConfigHelper::getSecret('smartpyme/config/services/bedrock', 'secret') ?: env('BEDROCK_SECRET'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-2'),
    ],
    
    'whatsapp' => [
        'access_token' => AwsConfigHelper::getSecret('smartpyme/config/services/whatsapp', 'access_token') ?: env('WHATSAPP_ACCESS_TOKEN'),
        'verify_token' => AwsConfigHelper::getSecret('smartpyme/config/services/whatsapp', 'verify_token') ?: env('WHATSAPP_VERIFY_TOKEN', 'smartpyme_verify_token'),
        'api_url' => AwsConfigHelper::getParameter('/smartpyme/config/services/whatsapp/api_url') ?: env('WHATSAPP_API_URL', 'https://graph.facebook.com/v21.0/131106576743413/messages'),
        'webhook_url' => AwsConfigHelper::getParameter('/smartpyme/config/services/whatsapp/webhook_url') ?: env('WHATSAPP_WEBHOOK_URL', '/api/whatsapp/webhook'),
        'dev_mode' => AwsConfigHelper::getParameter('/smartpyme/config/services/whatsapp/dev_mode') ?: env('WHATSAPP_DEV_MODE', true),
        'use_ai' => AwsConfigHelper::getParameter('/smartpyme/config/services/whatsapp/use_ai') ?: env('WHATSAPP_USE_AI', false),
        'use_whatsapp_business' => AwsConfigHelper::getParameter('/smartpyme/config/services/whatsapp/use_whatsapp_business') ?: env('WHATSAPP_USE_BUSINESS', true),
    ],
    
    'shopify' => [
        'enabled' => AwsConfigHelper::getParameter('/smartpyme/config/services/shopify/enabled') ?: env('SHOPIFY_ENABLED', false),
        'webhook_secret' => AwsConfigHelper::getSecret('smartpyme/config/services/shopify', 'webhook_secret') ?: env('SHOPIFY_WEBHOOK_SECRET'),
    ],

];
