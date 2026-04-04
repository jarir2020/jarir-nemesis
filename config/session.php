<?php
// Nemesis 4.0.0 | Added: 2026-04-02

return [
    'driver'    => env('SESSION_DRIVER', 'file'),
    'lifetime'  => (int) env('SESSION_LIFETIME', 120),   // minutes
    'expire_on_close' => false,
    'encrypt'   => false,
    'path'      => base_path('storage/session'),
    'cookie'    => env('SESSION_COOKIE', 'nemesis_session'),
    'secure'    => (bool) env('SESSION_SECURE_COOKIE', false),
    'http_only' => true,
    'same_site' => 'lax',   // 'strict' | 'lax' | 'none'
];
