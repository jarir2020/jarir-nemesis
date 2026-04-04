<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 2 — Multi-driver Database | Updated: 2026-04-03

namespace Nemesis\Core;

use PDO;
use PDOException;
use Nemesis\Database\Grammars\GrammarInterface;
use Nemesis\Database\Grammars\MySqlGrammar;
use Nemesis\Database\Grammars\PostgresGrammar;
use Nemesis\Database\Grammars\SQLiteGrammar;

class Database
{
    protected static ?PDO             $pdo     = null;
    protected static ?array           $config  = null;
    protected static array            $queryLog = [];
    protected static bool             $logging  = false;
    protected static ?GrammarInterface $grammar = null;

    // -------------------------------------------------------------------------
    // Query logging
    // -------------------------------------------------------------------------

    public static function enableQueryLog(): void
    {
        self::$logging = true;
    }

    public static function disableQueryLog(): void
    {
        self::$logging = false;
    }

    public static function getQueryLog(): array
    {
        return self::$queryLog;
    }

    public static function flushQueryLog(): void
    {
        self::$queryLog = [];
    }

    protected static function logQuery(string $sql, array $params = [], float $time = 0.0): void
    {
        if (self::$logging) {
            self::$queryLog[] = [
                'query'    => $sql,
                'bindings' => $params,
                'time'     => round($time * 1000, 2), // ms
            ];
        }
    }

    // -------------------------------------------------------------------------
    // Connection
    // -------------------------------------------------------------------------

    /**
     * Connect (or return existing connection).
     *
     * Config keys: driver, host, port, dbname, username, password
     * Supported drivers: mysql (default), pgsql, sqlite
     */
    public static function connect(array $config = null): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        if ($config === null && self::$config === null) {
            throw new \RuntimeException('Database configuration is required.');
        }

        if ($config !== null) {
            self::$config = $config;
        }

        $driver = strtolower(self::$config['driver'] ?? 'mysql');

        try {
            self::$pdo = match ($driver) {
                'mysql'  => self::connectMysql(),
                'pgsql'  => self::connectPgsql(),
                'sqlite' => self::connectSqlite(),
                default  => throw new \InvalidArgumentException("Unsupported DB driver: {$driver}"),
            };
        } catch (PDOException $e) {
            self::$pdo = null;
            if ((self::$config['debug'] ?? false) || getenv('APP_DEBUG') === 'true') {
                throw $e; // Let ErrorHandler render it
            }
            error_log('Database connection failed: ' . $e->getMessage());
            die('Internal Server Error: Database connection could not be established.');
        }

        return self::$pdo;
    }

    private static function connectMysql(): PDO
    {
        $host = self::$config['host'] ?? '127.0.0.1';
        $port = self::$config['port'] ?? 3306;
        $db   = self::$config['dbname'] ?? '';
        $dsn  = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        return new PDO($dsn, self::$config['username'] ?? '', self::$config['password'] ?? '', self::pdoOptions());
    }

    private static function connectPgsql(): PDO
    {
        $host = self::$config['host'] ?? '127.0.0.1';
        $port = self::$config['port'] ?? 5432;
        $db   = self::$config['dbname'] ?? '';
        $user = self::$config['username'] ?? '';
        $pass = self::$config['password'] ?? '';
        $dsn  = "pgsql:host={$host};port={$port};dbname={$db}";
        return new PDO($dsn, $user, $pass, self::pdoOptions());
    }

    private static function connectSqlite(): PDO
    {
        $path = self::$config['database'] ?? self::$config['dbname'] ?? ':memory:';
        $dsn  = "sqlite:{$path}";
        return new PDO($dsn, '', '', self::pdoOptions());
    }

    private static function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
    }

    // -------------------------------------------------------------------------
    // Grammar
    // -------------------------------------------------------------------------

    public static function getGrammar(): GrammarInterface
    {
        if (self::$grammar !== null) {
            return self::$grammar;
        }
        $driver = strtolower(self::$config['driver'] ?? 'mysql');
        self::$grammar = match ($driver) {
            'mysql'  => new MySqlGrammar(),
            'pgsql'  => new PostgresGrammar(),
            'sqlite' => new SQLiteGrammar(),
            default  => new MySqlGrammar(),
        };
        return self::$grammar;
    }

    /** Inject a grammar (useful in tests). */
    public static function setGrammar(GrammarInterface $grammar): void
    {
        self::$grammar = $grammar;
    }

    // -------------------------------------------------------------------------
    // CRUD helpers
    // -------------------------------------------------------------------------

    public static function view(string $sql, array $params = []): array
    {
        $pdo = self::connect();
        $start = microtime(true);
        $stmt  = $pdo->prepare($sql);
        $stmt->execute($params);
        self::logQuery($sql, $params, microtime(true) - $start);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(string $sql, array $params = []): int
    {
        $pdo  = self::connect();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function update(string $sql, array $params = []): int
    {
        $pdo  = self::connect();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function delete(string $sql, array $params = []): int
    {
        $pdo  = self::connect();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount(); // Fixed: was returning lastInsertId (wrong for DELETE)
    }

    public static function statement(string $sql, array $params = []): bool
    {
        $pdo  = self::connect();
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public static function unprepared(string $sql): int|false
    {
        return self::connect()->exec($sql);
    }

    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------

    public static function beginTransaction(): bool
    {
        return self::connect()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::connect()->commit();
    }

    public static function rollback(): bool
    {
        return self::connect()->rollBack();
    }

    public static function transaction(callable $callback): mixed
    {
        self::beginTransaction();
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollback();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public static function table(string $table): Fluent
    {
        return Fluent::table($table);
    }

    /** Disconnect (useful in tests). */
    public static function disconnect(): void
    {
        self::$pdo     = null;
        self::$grammar = null;
    }

    /** Replace the active connection (useful for SQLite in-memory tests). */
    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function getPdo(): ?PDO
    {
        return self::$pdo;
    }

    public static function getDriverName(): string
    {
        return strtolower(self::$config['driver'] ?? 'mysql');
    }
}
