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

    // Sender addresses offered in the Email Campaigns "SMTP Settings" provider
    // picker. Configurable per-environment so real addresses never need to be
    // hardcoded in frontend source. Comma-separated list of emails.
    'email_campaigns' => [
        'gmail_senders' => array_filter(array_map('trim', explode(',', env('EMAIL_CAMPAIGN_GMAIL_SENDERS', '')))),
        'outlook_senders' => array_filter(array_map('trim', explode(',', env('EMAIL_CAMPAIGN_OUTLOOK_SENDERS', '')))),
    ],

    // Local-only dev seeder credentials (DevelopmentSuperAdminSeeder). Never
    // referenced in production seeding — only set in your local .env.
    'dev_seeder' => [
        'super_admin_email'    => env('DEV_SUPER_ADMIN_EMAIL'),
        'super_admin_password' => env('DEV_SUPER_ADMIN_PASSWORD'),
    ],

];
