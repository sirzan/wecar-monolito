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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'base_url' => env('PAYPAL_API', 'https://api-m.sandbox.paypal.com'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'firecrawl' => [
        'key' => env('FIRECRAWL_API_KEY'),
    ],

    'apify' => [
        'token' => env('APIFY_TOKEN'),
        'facebook_enabled' => (bool) env('APIFY_FACEBOOK_ENABLED', false),
        'facebook_location' => env('APIFY_FB_LOCATION', 'mexicocity'),
        'facebook_results_limit' => (int) env('APIFY_FB_RESULTS_LIMIT', 12),
        'facebook_timeout' => (int) env('APIFY_FB_TIMEOUT_SECONDS', 90),
        'facebook_search_url' => env('APIFY_FB_SEARCH_URL'),
        'facebook_include_details' => (bool) env('APIFY_FB_INCLUDE_DETAILS', false),
    ],

    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', env('APP_URL')),
    ],

    'didit' => [
        'api_url' => env('DIDIT_API_URL', 'https://apx.didit.me'),
        'api_key' => env('DIDIT_API_KEY'),
        'workflow_id' => env('DIDIT_WORKFLOW_ID'),
        'shared_secret' => env('DIDIT_SHARED_SECRET'),
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
