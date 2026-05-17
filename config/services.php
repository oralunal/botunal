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

    'kick' => [
        'client_id' => env('KICK_CLIENT_ID'),
        'client_secret' => env('KICK_CLIENT_SECRET'),
        'redirect_uri' => env('KICK_REDIRECT_URI', env('APP_URL').'/kick/oauth/callback'),
        'channel_slug' => env('KICK_CHANNEL_SLUG', 'trolunal'),
        'bot_slug' => env('KICK_BOT_SLUG', 'botunal'),
        'send_as' => env('KICK_SEND_AS', 'bot'),
        'command_prefix' => env('KICK_COMMAND_PREFIX', '!'),
        'webhook_tolerance_seconds' => (int) env('KICK_WEBHOOK_TOLERANCE', 300),
        'public_key_cache_ttl' => (int) env('KICK_PUBLIC_KEY_TTL', 86400),
        'urls' => [
            'authorize' => 'https://id.kick.com/oauth/authorize',
            'token' => 'https://id.kick.com/oauth/token',
            'api_base' => 'https://api.kick.com/public/v1',
            'public_key' => 'https://api.kick.com/public/v1/public-key',
        ],
    ],

];
