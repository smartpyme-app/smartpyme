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

    'bedrock' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-2'),
        'model_id' => env('BEDROCK_MODEL_ID', 'anthropic.claude-3-5-haiku-20241022-v1:0'),
        'inference_profile_arn' => env('BEDROCK_INFERENCE_PROFILE_ARN', 'us.anthropic.claude-3-5-haiku-20241022-v1:0'),
        'max_tokens' => env('BEDROCK_MAX_TOKENS', 500),
        'temperature' => env('BEDROCK_TEMPERATURE', 0.7),
        'top_p' => env('BEDROCK_TOP_P', 0.9),
        'top_k' => env('BEDROCK_TOP_K', 250),
        'system_prompt' => env('BEDROCK_SYSTEM_PROMPT', 'Tu eres un asistente financiero experto con conocimientos de contabilidad, finanzas y análisis de negocio.'),
    ],

];
