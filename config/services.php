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

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ai_text' => [
        'driver' => env('AI_TEXT_DRIVER', 'fake'),
        'api_key' => env('AI_TEXT_API_KEY'),
        'model' => env('AI_TEXT_MODEL', 'gpt-4o-mini'),
        'base_url' => env('AI_TEXT_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('AI_TEXT_TIMEOUT', 30),
        'retries' => (int) env('AI_TEXT_RETRIES', 2),
    ],

];
