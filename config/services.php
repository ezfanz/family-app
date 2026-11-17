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

    /*
    | Yen to Ringgit rate. Bank Negara Malaysia's official API first, the
    | European Central Bank (via Frankfurter) as a backup. No API keys needed.
    */
    'exchange_rates' => [
        'bnm_url' => env('BNM_API_URL', 'https://api.bnm.gov.my/public'),
        'frankfurter_url' => env('FRANKFURTER_API_URL', 'https://api.frankfurter.dev/v1'),
        'cache_minutes' => (int) env('EXCHANGE_RATE_CACHE_MINUTES', 180),
    ],

    /*
    | Claude API, used to read receipt photos (config/tax_refund.php).
    */
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

];
