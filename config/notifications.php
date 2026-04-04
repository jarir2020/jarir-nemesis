<?php
// Nemesis 5.0.0 | Phase 14 — Notifications config | Created: 2026-04-03

return [

    /*
    |--------------------------------------------------------------------------
    | Default Channels
    |--------------------------------------------------------------------------
    | Channels used when a notification does not override via().
    | Supported: 'mail', 'database', 'log', 'broadcast', 'slack', 'webhook'
    */
    'default_channels' => ['log'],

    /*
    |--------------------------------------------------------------------------
    | Database Channel
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table' => 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Channel
    |--------------------------------------------------------------------------
    */
    'mail' => [
        'from'      => env('MAIL_FROM', 'noreply@example.com'),
        'from_name' => env('MAIL_FROM_NAME', 'Nemesis'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack Channel
    |--------------------------------------------------------------------------
    */
    'slack' => [
        'webhook_url' => env('SLACK_WEBHOOK_URL', ''),
    ],

];
