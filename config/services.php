<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // AI assistant (payee Q&A). Unset by default — the assistant runs as a stub
    // until a provider key is supplied. See docs/INTEGRATIONS.md.
    'ai' => [
        'key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL', 'claude-sonnet-5'),
    ],

    // Single-sign-on. Unset by default; enabling requires an OAuth/SAML provider
    // (e.g. Laravel Socialite) plus the provider's client credentials.
    'sso' => [
        'enabled' => env('SSO_ENABLED', false),
        'provider' => env('SSO_PROVIDER'),
        'client_id' => env('SSO_CLIENT_ID'),
        'client_secret' => env('SSO_CLIENT_SECRET'),
    ],

];
