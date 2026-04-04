<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 12 — Core CMS | Created: 2026-04-03
// Meta key-value store — per-entity arbitrary metadata (DB-backed, memory fallback).

namespace Nemesis\CMS;

/**
 * MetaStore — attach arbitrary key-value metadata to any entity/model.
 *
 * DB table (auto-created on first use if DB is available):
 *   meta_items (id, meta_type, meta_id, meta_key, meta_value, created_at, updated_at)
 *
 * Usage:
 *   MetaStore::set('post', 42, 'color', 'blue');
 *   MetaStore::get('post', 42, 'color');          // → 'blue'
 *   MetaStore::all('post', 42);                   // → ['color' => 'blue', ...]
 *   MetaStore::delete('post', 42, 'color');
 *   MetaStore::exists('post', 42, 'color');       // → false
 *
 * Values are JSON-serialised before storage and decoded on retrieval,
 * so any serialisable PHP value is supported.
 */
class MetaStore
{
    // In-memory store used when DB is unavailable (unit tests, CLI, etc.)
    // Structure: [type][id][key] = value
    private static array $memory = [];

    /** Injected PDO-compatible connection (optional). */
    private static mixed $db = null;

    /** Table name (configurable). */
    private static string $table = 'meta_items';

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /** Inject a PDO (or Nemesis Database) connection for persistence. */
    public static function setDb(mixed $db): void
    {
        static::$db = $db;
    }

    public static function setTable(string $table): void
    {
        static::$table = $table;
    }

    /** Flush in-memory cache AND disconnect DB (test helper). */
    public static function reset(): void
    {
        static::$memory = [];
        static::$db     = null;
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Store a meta value.
     *
     * @param  string $type  Entity type, e.g. 'post', 'user'
     * @param  int    $id    Entity ID
     * @param  string $key   Meta key
     * @param  mixed  $value Any JSON-serialisable value
     */
    public static function set(string $type, int $id, string $key, mixed $value): void
    {
        // Always keep memory copy (fast reads, test support)
        static::$memory[$type][$id][$key] = $value;

        if (static::$db !== null) {
            static::ensureTable();
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $now     = date('Y-m-d H:i:s');

            try {
                // Upsert: delete then insert (portable across MySQL / SQLite)
                static::dbExecute(
                    'DELETE FROM ' . static::$table . ' WHERE meta_type=? AND meta_id=? AND meta_key=?',
                    [$type, $id, $key]
                );
                static::dbExecute(
                    'INSERT INTO ' . static::$table . ' (meta_type,meta_id,meta_key,meta_value,created_at,updated_at) VALUES (?,?,?,?,?,?)',
                    [$type, $id, $key, $encoded, $now, $now]
                );
            } catch (\Throwable) {
                // DB failure — in-memory value is still set, carry on
            }
        }
    }

    /**
     * Retrieve a meta value.
     *
     * @param  mixed $default Returned when the key is not set
     */
    public static function get(string $type, int $id, string $key, mixed $default = null): mixed
    {
        // Fast path: memory
        if (isset(static::$memory[$type][$id][$key])) {
            return static::$memory[$type][$id][$key];
        }

        if (static::$db !== null) {
            static::ensureTable();
            try {
                $row = static::dbFetchOne(
                    'SELECT meta_value FROM ' . static::$table . ' WHERE meta_type=? AND meta_id=? AND meta_key=? LIMIT 1',
                    [$type, $id, $key]
                );
                if ($row !== null) {
                    $value = json_decode((string) ($row['meta_value'] ?? $row[0] ?? ''), true, 512, JSON_THROW_ON_ERROR);
                    // Cache in memory for subsequent reads
                    static::$memory[$type][$id][$key] = $value;
                    return $value;
                }
            } catch (\Throwable) {}
        }

        return $default;
    }

    /**
     * Return all meta key-value pairs for an entity as an associative array.
     */
    public static function all(string $type, int $id): array
    {
        $result = static::$memory[$type][$id] ?? [];

        if (static::$db !== null) {
            static::ensureTable();
            try {
                $rows = static::dbFetchAll(
                    'SELECT meta_key, meta_value FROM ' . static::$table . ' WHERE meta_type=? AND meta_id=?',
                    [$type, $id]
                );
                foreach ($rows as $row) {
                    $k = $row['meta_key'] ?? $row[0] ?? null;
                    $v = $row['meta_value'] ?? $row[1] ?? null;
                    if ($k !== null && !isset($result[$k])) {
                        $result[$k] = json_decode((string) $v, true, 512, JSON_THROW_ON_ERROR);
                    }
                }
            } catch (\Throwable) {}
        }

        return $result;
    }

    /**
     * Delete a meta key for an entity.
     */
    public static function delete(string $type, int $id, string $key): void
    {
        unset(static::$memory[$type][$id][$key]);

        if (static::$db !== null) {
            static::ensureTable();
            try {
                static::dbExecute(
                    'DELETE FROM ' . static::$table . ' WHERE meta_type=? AND meta_id=? AND meta_key=?',
                    [$type, $id, $key]
                );
            } catch (\Throwable) {}
        }
    }

    /**
     * Check if a meta key exists for an entity.
     */
    public static function exists(string $type, int $id, string $key): bool
    {
        if (isset(static::$memory[$type][$id][$key])) return true;

        if (static::$db !== null) {
            static::ensureTable();
            try {
                $row = static::dbFetchOne(
                    'SELECT 1 FROM ' . static::$table . ' WHERE meta_type=? AND meta_id=? AND meta_key=? LIMIT 1',
                    [$type, $id, $key]
                );
                return $row !== null;
            } catch (\Throwable) {}
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // DB helpers (auto-create table, portable PDO / Nemesis DB wrapper)
    // -------------------------------------------------------------------------

    private static bool $tableChecked = false;

    private static function ensureTable(): void
    {
        if (static::$tableChecked) return;
        static::$tableChecked = true;

        $sql = 'CREATE TABLE IF NOT EXISTS ' . static::$table . ' (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            meta_type  VARCHAR(100)  NOT NULL,
            meta_id    INTEGER       NOT NULL,
            meta_key   VARCHAR(255)  NOT NULL,
            meta_value LONGTEXT,
            created_at DATETIME,
            updated_at DATETIME,
            KEY_meta   UNIQUE (meta_type, meta_id, meta_key)
        )';

        // MySQL / MariaDB variant (no AUTOINCREMENT keyword; use AUTO_INCREMENT)
        // We try both; PDO will throw on syntax issues and we silently continue.
        try {
            static::dbExecute($sql, []);
        } catch (\Throwable) {
            try {
                $sqlMysql = str_replace(
                    ['INTEGER PRIMARY KEY AUTOINCREMENT', 'KEY_meta   UNIQUE'],
                    ['INT PRIMARY KEY AUTO_INCREMENT', 'UNIQUE KEY KEY_meta'],
                    $sql
                );
                static::dbExecute($sqlMysql, []);
            } catch (\Throwable) {}
        }
    }

    /** Execute a statement via PDO or Nemesis Database. */
    private static function dbExecute(string $sql, array $params): void
    {
        $db = static::$db;
        if ($db instanceof \PDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } elseif (method_exists($db, 'statement')) {
            // Nemesis\Core\Database style
            $db->statement($sql, $params);
        } elseif (method_exists($db, 'execute')) {
            $db->execute($sql, $params);
        }
    }

    /** Fetch a single row as associative array, or null. */
    private static function dbFetchOne(string $sql, array $params): ?array
    {
        $db = static::$db;
        if ($db instanceof \PDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } elseif (method_exists($db, 'selectOne')) {
            return $db->selectOne($sql, $params) ?: null;
        }
        return null;
    }

    /** Fetch all rows. */
    private static function dbFetchAll(string $sql, array $params): array
    {
        $db = static::$db;
        if ($db instanceof \PDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } elseif (method_exists($db, 'select')) {
            return (array) $db->select($sql, $params);
        }
        return [];
    }
}
