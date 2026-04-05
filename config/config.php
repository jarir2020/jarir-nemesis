<?php

// Nemesis 5.0.0 | Updated: 2026-04-06 — SQLite default, MySQL kept as commented option

$driver = strtolower(getenv('DB_DRIVER') ?: 'sqlite');

return [
    'database' => [
        'driver'   => $driver,

        // SQLite — default driver (zero-config, file-based)
        'database' => getenv('DB_DATABASE') ?: __DIR__ . '/../database/nemesis.sqlite',

        // MySQL / PostgreSQL (used when DB_DRIVER=mysql or pgsql)
        'host'     => getenv('DB_HOST')     ?: '127.0.0.1',
        'port'     => (int) (getenv('DB_PORT') ?: ($driver === 'pgsql' ? 5432 : 3306)),
        'dbname'   => getenv('DB_NAME')     ?: 'nemesis',
        'username' => getenv('DB_USER')     ?: 'root',
        'password' => getenv('DB_PASS')     ?: '',
    ]
];
