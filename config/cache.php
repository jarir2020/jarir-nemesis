<?php
// Nemesis 4.0.0 | Added: 2026-04-02

return [
    'default' => env('CACHE_DRIVER', 'file'),

    'stores' => [
        'file' => [
            'driver' => 'file',
            'path'   => base_path('storage/framework/cache'),
        ],
        'redis' => [
            'driver'   => 'redis',
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'port'     => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => (int) env('REDIS_CACHE_DB', 1),
        ],
        'database' => [
            'driver' => 'database',
            'table'  => 'cache',
        ],
        'array' => [
            'driver' => 'array',  // in-memory, useful for tests
        ],
    ],

    'prefix' => env('CACHE_PREFIX', 'nemesis_'),
    'ttl'    => (int) env('CACHE_TTL', 3600),
];
