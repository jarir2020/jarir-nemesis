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
    protected static ?PDO             $pdo      = null;
    protected static ?array           $config   = null;
    protected static array            $pdoPool  = [];
    protected static array            $configPool = [];
    protected static string           $defaultConnection = 'default';
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
    public static function connect(array $config = null, ?string $connection = null): PDO
    {
        if ($config !== null) {
            self::configure($config, $connection);
        }

        if (empty(self::$configPool) && self::$config === null) {
            $loaded = Config::get('config.database');
            if (is_array($loaded) && !empty($loaded)) {
                self::configure($loaded);
            }
        }

        $connection = self::normalizeConnectionName($connection);
        if ($connection === '') {
            $connection = self::$defaultConnection;
        }

        if (isset(self::$pdoPool[$connection])) {
            return self::$pdoPool[$connection];
        }

        $config = self::resolveConnectionConfig($connection);
        if ($config === []) {
            throw new \RuntimeException('Database configuration is required.');
        }

        $driver = strtolower($config['driver'] ?? 'mysql');

        try {
            $pdo = match ($driver) {
                'mysql'  => self::connectMysql($config),
                'pgsql'  => self::connectPgsql($config),
                'sqlite' => self::connectSqlite($config),
                default  => throw new \InvalidArgumentException("Unsupported DB driver: {$driver}"),
            };
            self::$pdoPool[$connection] = $pdo;
            if ($connection === self::$defaultConnection || self::$pdo === null) {
                self::$pdo = $pdo;
            }
        } catch (PDOException $e) {
            unset(self::$pdoPool[$connection]);
            if ($connection === self::$defaultConnection) {
                self::$pdo = null;
            }
            if ((self::$config['debug'] ?? false) || getenv('APP_DEBUG') === 'true') {
                throw $e; // Let ErrorHandler render it
            }
            error_log('Database connection failed: ' . $e->getMessage());
            die('Internal Server Error: Database connection could not be established.');
        }

        return self::$pdoPool[$connection];
    }

    public static function connection(?string $connection = null): PDO
    {
        return self::connect(null, $connection);
    }

    public static function configure(array $config, ?string $defaultConnection = null): void
    {
        self::$pdo = null;
        self::$pdoPool = [];
        self::$configPool = [];
        self::$grammar = null;

        if (isset($config['connections']) && is_array($config['connections']) && !empty($config['connections'])) {
            foreach ($config['connections'] as $name => $connectionConfig) {
                if (is_array($connectionConfig)) {
                    self::$configPool[self::normalizeConnectionName((string) $name)] = $connectionConfig;
                }
            }

            $fallback = $defaultConnection
                ?? (string) ($config['default_connection'] ?? '')
                ?? array_key_first(self::$configPool)
                ?? self::$defaultConnection;

            self::$defaultConnection = self::normalizeConnectionName((string) $fallback) ?: self::$defaultConnection;
            $resolvedConfig = self::$configPool[self::$defaultConnection] ?? null;
            if (!is_array($resolvedConfig)) {
                $resolvedConfig = self::$configPool !== [] ? reset(self::$configPool) : null;
            }
            self::$config = is_array($resolvedConfig) ? $resolvedConfig : null;
            return;
        }

        $name = $defaultConnection
            ?? (string) ($config['name'] ?? $config['connection'] ?? self::$defaultConnection);

        $name = self::normalizeConnectionName($name) ?: self::$defaultConnection;
        self::$defaultConnection = $name;
        self::$configPool[$name] = $config;
        self::$config = $config;
    }

    public static function setConnectionConfig(string $name, array $config, bool $makeDefault = false): void
    {
        $name = self::normalizeConnectionName($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Connection name is required.');
        }

        self::$configPool[$name] = $config;
        if ($makeDefault || self::$defaultConnection === '') {
            self::$defaultConnection = $name;
            self::$config = $config;
        }
    }

    public static function hasConnection(string $name): bool
    {
        return array_key_exists(self::normalizeConnectionName($name), self::$configPool);
    }

    public static function getConnectionNames(): array
    {
        return array_keys(self::$configPool);
    }

    private static function connectMysql(array $config): PDO
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $db   = $config['dbname'] ?? '';
        $dsn  = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        return new PDO($dsn, $config['username'] ?? '', $config['password'] ?? '', self::pdoOptions());
    }

    private static function connectPgsql(array $config): PDO
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 5432;
        $db   = $config['dbname'] ?? '';
        $user = $config['username'] ?? '';
        $pass = $config['password'] ?? '';
        $dsn  = "pgsql:host={$host};port={$port};dbname={$db}";
        return new PDO($dsn, $user, $pass, self::pdoOptions());
    }

    private static function connectSqlite(array $config): PDO
    {
        // Updated: 2026-04-06 — resolve path, auto-create parent directory
        $path = $config['database'] ?? $config['dbname'] ?? ':memory:';

        if ($path !== ':memory:') {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        return new PDO("sqlite:{$path}", '', '', self::pdoOptions());
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

    public static function view(string $sql, array $params = [], ?string $connection = null): array
    {
        $pdo = self::connection($connection);
        $start = microtime(true);
        $stmt  = $pdo->prepare($sql);
        $stmt->execute($params);
        self::logQuery($sql, $params, microtime(true) - $start);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(string $sql, array $params = [], ?string $connection = null): int
    {
        $pdo  = self::connection($connection);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function update(string $sql, array $params = [], ?string $connection = null): int
    {
        $pdo  = self::connection($connection);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function delete(string $sql, array $params = [], ?string $connection = null): int
    {
        $pdo  = self::connection($connection);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount(); // Fixed: was returning lastInsertId (wrong for DELETE)
    }

    public static function statement(string $sql, array $params = [], ?string $connection = null): bool
    {
        $pdo  = self::connection($connection);
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public static function unprepared(string $sql, ?string $connection = null): int|false
    {
        return self::connection($connection)->exec($sql);
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
        self::$pdoPool = [];
        self::$grammar = null;
    }

    /** Replace the active connection (useful for SQLite in-memory tests). */
    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
        self::$pdoPool[self::$defaultConnection] = $pdo;
    }

    public static function getPdo(): ?PDO
    {
        return self::$pdo;
    }

    public static function getDriverName(): string
    {
        return strtolower(self::$config['driver'] ?? 'mysql');
    }

    private static function resolveConnectionConfig(?string $connection = null): array
    {
        $connection = self::normalizeConnectionName($connection ?? self::$defaultConnection);
        if ($connection !== '' && isset(self::$configPool[$connection])) {
            return self::$configPool[$connection];
        }

        if (self::$config !== null && $connection === self::$defaultConnection) {
            return self::$config;
        }

        return [];
    }

    private static function normalizeConnectionName(?string $name): string
    {
        return strtolower(trim((string) $name));
    }
}
