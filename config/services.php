<?php

return [

    'quran' => [
        'base_url' => env('QURAN_BASE_URL', 'https://api.alquran.cloud/v1'),
        'connect_timeout' => 3,
        'timeout' => 8,
        'cache_ttl' => ['metadata' => 604800, 'ayahs' => 86400, 'audio' => 86400],
    ],

    'aladhan' => [
        'base_url' => env('ALADHAN_BASE_URL', 'https://api.aladhan.com/v1'),
        'connect_timeout' => 3,
        'timeout' => 8,
        'cache_ttl' => ['prayer_times' => 1800, 'calendar' => 86400, 'qibla' => 3600],
    ],

    'google' => [
        'client_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('GOOGLE_CLIENT_IDS', ''))))),
    ],

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

];
