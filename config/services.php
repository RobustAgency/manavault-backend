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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'key' => env('SUPABASE_SERVICE_KEY'), // Service key has admin privileges
        'jwt_secret' => env('SUPABASE_JWT_SECRET'),
    ],

    'ez_cards' => [
        'base_url' => env('EZ_CARDS_BASE_URL', 'https://api.ezcards.io'),
        'api_key' => env('EZ_CARDS_API_KEY'),
        'access_token' => env('EZ_CARDS_ACCESS_TOKEN'),
    ],

    'irewardify' => [
        'base_url' => env('IREWARDIFY_BASE_URL', 'https://api.irewardify.com'),
    ],

    'gift2games' => [
        'base_url' => env('GIFT_2_GAMES_BASE_URL', 'https://gift2games.net/api/'),
        'access_token' => env('GIFT_2_GAMES_ACCESS_TOKEN'),
    ],

    'voucher' => [
        'encryption_key' => env('VOUCHER_ENCRYPTION_KEY', 'CKunc0FMA96tKqFiowsKv1H1VCzQM6G8WLgzVKnyVAo='),
    ],

];
