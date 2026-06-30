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
        'from_address' => env('RESEND_FROM_ADDRESS'),
        'from_name' => env('RESEND_FROM_NAME', 'RM Soft'),

        'reply_to' => env('RESEND_REPLY_TO'),

    ],

    'proforma_bulk_send_delay_seconds' => (int) env('PROFORMA_BULK_SEND_DELAY_SECONDS', 2),

    'directorio_api' => [
        'url' => env('DIRECTORIO_API_URL'),
        'token' => env('DIRECTORIO_API_TOKEN'),
        'timeout' => (int) env('DIRECTORIO_API_TIMEOUT', 10),
        'verify_ssl' => env('DIRECTORIO_API_VERIFY_SSL', true),
        'force_api' => env('DIRECTORIO_FORZAR_API', false),
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
