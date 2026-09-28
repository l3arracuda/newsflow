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

    'image_generation' => [
        'driver' => env('IMAGE_GENERATION_DRIVER', 'manual'),
        'api_key' => env('IMAGE_GENERATION_API_KEY'),
        'model' => env('IMAGE_GENERATION_MODEL', 'gpt-image-1'),
        'base_url' => env('IMAGE_GENERATION_BASE_URL', 'https://api.openai.com/v1'),
        'size' => env('IMAGE_GENERATION_SIZE', '1024x1024'),
        'quality' => env('IMAGE_GENERATION_QUALITY', 'low'),
        'timeout' => (int) env('IMAGE_GENERATION_TIMEOUT', 90),
        'retries' => (int) env('IMAGE_GENERATION_RETRIES', 1),
        'disk' => env('IMAGE_GENERATION_DISK', 'local'),
        'max_bytes' => (int) env('IMAGE_GENERATION_MAX_BYTES', 20971520),
        'fake_fail' => (bool) env('IMAGE_GENERATION_FAKE_FAIL', false),
    ],

    'facebook' => [
        'driver' => env('PUBLISH_DRIVER', 'fake'),
        'auto_publish' => (bool) env('AUTO_PUBLISH', false),
        'graph_version' => env('META_GRAPH_VERSION'),
        'page_id' => env('META_PAGE_ID'),
        'page_access_token' => env('META_PAGE_ACCESS_TOKEN'),
        'timeout' => (int) env('META_PUBLISH_TIMEOUT', 30),
    ],

];
