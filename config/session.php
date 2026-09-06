<?php
// Nemesis 4.0.0 | Added: 2026-04-02

return [
    'driver'    => env('SESSION_DRIVER', 'file'),
    'lifetime'  => (int) env('SESSION_LIFETIME', 120),   // minutes
    'expire_on_close' => false,
    'encrypt'   => false,
    'path'      => env('SESSION_PATH', env('SESSION_SAVE_PATH', base_path('storage/session'))),
    'cookie'    => env('SESSION_COOKIE', 'nemesis_session'),
    'cookie_path' => env('SESSION_COOKIE_PATH', '/'),
    'domain'    => env('SESSION_COOKIE_DOMAIN', ''),
    'http_only' => (bool) env('SESSION_HTTP_ONLY', true),
    'secure'    => (bool) env('SESSION_SECURE_COOKIE', false),
    'same_site' => env('SESSION_SAME_SITE', 'lax'),   // 'strict' | 'lax' | 'none'
];
