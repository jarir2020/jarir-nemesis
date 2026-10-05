<?php
// Nemesis 7.2 — password reset configuration

return [
    'passwords' => [
        // The token column stores a SHA-256 digest, never the raw URL token.
        'table' => env('PASSWORD_RESET_TABLE', 'password_resets'),
        // Expiry is expressed in minutes.
        'expire' => (int) env('PASSWORD_RESET_EXPIRE', 60),
    ],
];
