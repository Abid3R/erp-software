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
    |--------------------------------------------------------------------------
    | Gemini AI assistant (optional)
    |--------------------------------------------------------------------------
    | Google Gemini powers the in-panel AI assistant. It is DISABLED unless an
    | API key is present, so the ERP behaves exactly as before until configured.
    | Get a free key at https://aistudio.google.com/apikey (free tier is rate-
    | limited; on the free tier Google may use prompts to improve their products,
    | so the assistant sends only aggregate figures — never full data tables).
    */
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
        // When false, party (customer/supplier) names are replaced with generic
        // labels before anything is sent to Google — extra privacy, less detail.
        'share_party_names' => (bool) env('GEMINI_SHARE_PARTY_NAMES', true),
    ],

];
