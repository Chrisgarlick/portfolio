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

    /*
    | Typeset: markdown or JSON layout in, PDF or DOCX out. Renders gated
    | resources and audit deliverables. Without a key, downloads that need a
    | render answer 503 rather than failing obscurely.
    */
    'typeset' => [
        'url' => env('TYPESET_API_URL', 'https://typeset.chrisgarlick.com'),
        'key' => env('TYPESET_API_KEY'),
        'audit_client' => env('TYPESET_AUDIT_CLIENT', 'chrisgarlick'),
        'timeout' => (int) env('TYPESET_TIMEOUT', 60),
    ],

    /*
    | The Kritano platform API behind the free site-audit tool. Without a key
    | the tool returns clearly-labelled mock scores, as the live site did, so
    | the page works in development.
    */
    'kritano' => [
        'url' => env('KRITANO_PLATFORM_API_URL', 'https://kritano.com/api/v1'),
        'key' => env('KRITANO_API'),
    ],

];
