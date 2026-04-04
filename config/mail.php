<?php
// Nemesis 4.0.0 | Added: 2026-04-02

return [
    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [
        'smtp' => [
            'transport'  => 'smtp',
            'host'       => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port'       => (int) env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username'   => env('MAIL_USER', null),
            'password'   => env('MAIL_PASS', null),
            'timeout'    => 10,
        ],
        'log' => [
            'transport' => 'log',   // writes mail to storage/logs/mail.log
        ],
        'array' => [
            'transport' => 'array', // captures mail in-memory (use with MailFake)
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM', 'hello@example.com'),
        'name'    => env('MAIL_FROM_NAME', 'Nemesis'),
    ],
];
