<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 9 — Typed Config DTOs | Updated: 2026-04-06 — SQLite support

namespace Nemesis\Config;

readonly class DatabaseConfig
{
    public function __construct(
        public string $driver,
        public string $database, // SQLite file path (or ':memory:')
        public string $host,
        public int    $port,
        public string $dbname,
        public string $username,
        public string $password,
        public string $charset,
        public string $collation,
    ) {}

    public static function fromEnv(): static
    {
        $driver = strtolower((string) (getenv('DB_DRIVER') ?: 'sqlite'));
        return new static(
            driver:    $driver,
            database:  (string) (getenv('DB_DATABASE') ?: ''),
            host:      (string) (getenv('DB_HOST')     ?: '127.0.0.1'),
            port:      (int)    (getenv('DB_PORT')     ?: self::defaultPort($driver)),
            dbname:    (string) (getenv('DB_NAME')     ?: ''),
            username:  (string) (getenv('DB_USER')     ?: ''),
            password:  (string) (getenv('DB_PASS')     ?: ''),
            charset:   (string) (getenv('DB_CHARSET')  ?: 'utf8mb4'),
            collation: (string) (getenv('DB_COLLATION')?: 'utf8mb4_unicode_ci'),
        );
    }

    private static function defaultPort(string $driver): int
    {
        return match ($driver) {
            'pgsql'  => 5432,
            'sqlite' => 0,
            default  => 3306,
        };
    }

    public function toArray(): array
    {
        return [
            'driver'   => $this->driver,
            'database' => $this->database,
            'host'     => $this->host,
            'port'     => $this->port,
            'dbname'   => $this->dbname,
            'username' => $this->username,
            'password' => $this->password,
        ];
    }

    /** Required env keys — SQLite only needs DB_DRIVER; MySQL/pgsql need host + credentials. */
    public static function required(): array
    {
        $driver = strtolower((string) (getenv('DB_DRIVER') ?: 'sqlite'));
        if ($driver === 'sqlite') {
            return ['DB_DRIVER'];
        }
        return ['DB_DRIVER', 'DB_HOST', 'DB_NAME', 'DB_USER'];
    }
}
