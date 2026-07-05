<?php
// Nemesis 6.2.1 | Added: 2026-04-02

return [
    'name'     => env('APP_NAME', 'Nemesis'),
    'env'      => env('APP_ENV', 'production'),
    'debug'    => (bool) env('APP_DEBUG', false),
    'json_pretty' => env('APP_JSON_PRETTY', true),
    'url'      => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale'   => env('APP_LOCALE', 'en'),
    'key'      => env('APP_KEY', ''),
    'cipher'   => 'AES-256-CBC',
];
