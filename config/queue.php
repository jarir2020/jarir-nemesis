<?php
// Nemesis 4.0.0 | Added: 2026-04-02

return [
    'default' => env('QUEUE_DRIVER', 'database'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',  // runs job immediately inline (no worker needed)
        ],
        'database' => [
            'driver'     => 'database',
            'table'      => 'jobs',
            'queue'      => 'default',
            'retry_after' => 90,
        ],
        'redis' => [
            'driver'     => 'redis',
            'connection' => 'default',
            'queue'      => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for'  => null,
        ],
    ],

    'failed' => [
        'driver' => 'database',
        'table'  => 'failed_jobs',
    ],
];
