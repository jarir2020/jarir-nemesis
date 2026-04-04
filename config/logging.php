<?php
// Nemesis 4.0.0 | Added: 2026-04-02

return [
    'default' => env('LOG_CHANNEL', 'daily'),

    'channels' => [
        'single' => [
            'driver' => 'single',
            'path'   => base_path('storage/logs/nemesis.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
        ],
        'daily' => [
            'driver' => 'daily',
            'path'   => base_path('storage/logs/nemesis.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'days'   => (int) env('LOG_DAYS', 14),
        ],
        'stderr' => [
            'driver' => 'monolog',
            'level'  => 'debug',
        ],
        'null' => [
            'driver' => 'monolog',  // discard all — use in testing
        ],
    ],
];
