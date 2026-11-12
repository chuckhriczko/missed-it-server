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


    // Logalyzer (log monitoring). Leave the URL or token empty to send nothing.
    'logalyzer' => [
        'url' => env('LOGALYZER_URL'),
        'token' => env('LOGALYZER_TOKEN'),
        'timeout' => max(0.1, min(5.0, (float) env('LOGALYZER_TIMEOUT', 1))),
        'project_id' => env('LOGALYZER_PROJECT_ID'),
        'environment' => env('LOGALYZER_ENVIRONMENT', env('APP_ENV', 'production')),
        'server_log' => env('LOGALYZER_LOG_NAME', 'Missed It · Puzzle Garden server'),
        'game_log' => env('LOGALYZER_GAME_LOG_NAME', 'Missed It · Game'),
    ],

];
