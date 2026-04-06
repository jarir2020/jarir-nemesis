<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 16 — API Key management: create, rotate, revoke, scope | 2026-04-06

namespace Nemesis\Auth;

use Nemesis\Core\Database;

class ApiKey
{
    private const PREFIX     = 'nms_';
    private const KEY_BYTES  = 32; // 64 hex chars

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    /**
     * Create a new API key for a user.
     *
     * @param  int|string $userId
     * @param  string[]   $scopes   e.g. ['read', 'write']
     * @param  string     $name     Human label ("Mobile app key")
     * @param  int|null   $expiresIn seconds until expiry, null = never
     * @return array{key: string, id: int}  key shown ONCE — store it!
     */
    public static function create(
        int|string $userId,
        array $scopes = ['*'],
        string $name = '',
        ?int $expiresIn = null
    ): array {
        self::ensureTable();

        $raw    = self::PREFIX . bin2hex(random_bytes(self::KEY_BYTES));
        $hash   = hash('sha256', $raw);
        $prefix = substr($raw, 0, 8); // visible prefix for recognition
        $expiresAt = $expiresIn ? date('Y-m-d H:i:s', time() + $expiresIn) : null;

        Database::statement(
            "INSERT INTO api_keys (user_id, name, key_prefix, key_hash, scopes, expires_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$userId, $name, $prefix, $hash, json_encode($scopes), $expiresAt]
        );

        $id = (int) Database::connect()->lastInsertId();

        return ['key' => $raw, 'id' => $id, 'prefix' => $prefix];
    }

    // -------------------------------------------------------------------------
    // Verify
    // -------------------------------------------------------------------------

    /**
     * Verify a raw API key. Returns the key row or null.
     */
    public static function verify(string $rawKey): ?array
    {
        self::ensureTable();

        if (!str_starts_with($rawKey, self::PREFIX)) {
            return null;
        }

        $hash = hash('sha256', $rawKey);
        $rows = Database::view(
            "SELECT * FROM api_keys WHERE key_hash = ? AND revoked = 0 LIMIT 1",
            [$hash]
        );

        if (empty($rows)) return null;

        $row = $rows[0];

        // Check expiry
        if ($row['expires_at'] && strtotime($row['expires_at']) < time()) {
            return null;
        }

        // Update last used
        Database::statement(
            "UPDATE api_keys SET last_used_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $row['id']]
        );

        // Decode scopes
        $row['scopes'] = json_decode($row['scopes'] ?? '["*"]', true);

        return $row;
    }

    /**
     * Check if a verified key row has a required scope.
     */
    public static function hasScope(array $keyRow, string $scope): bool
    {
        $scopes = $keyRow['scopes'] ?? [];
        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }

    // -------------------------------------------------------------------------
    // Rotate
    // -------------------------------------------------------------------------

    /**
     * Revoke old key and issue a new one with the same settings.
     * Returns the new raw key.
     */
    public static function rotate(int $keyId): array
    {
        self::ensureTable();

        $rows = Database::view("SELECT * FROM api_keys WHERE id = ? LIMIT 1", [$keyId]);
        if (empty($rows)) {
            throw new \RuntimeException("API key #{$keyId} not found.");
        }

        $old = $rows[0];

        // Revoke old
        Database::statement("UPDATE api_keys SET revoked = 1 WHERE id = ?", [$keyId]);

        // Create new with same settings
        return self::create(
            $old['user_id'],
            json_decode($old['scopes'] ?? '["*"]', true),
            $old['name'] . ' (rotated)',
            $old['expires_at'] ? (strtotime($old['expires_at']) - time()) : null
        );
    }

    // -------------------------------------------------------------------------
    // Revoke
    // -------------------------------------------------------------------------

    public static function revoke(int $keyId): bool
    {
        self::ensureTable();
        return Database::update(
            "UPDATE api_keys SET revoked = 1 WHERE id = ?",
            [$keyId]
        ) > 0;
    }

    /**
     * Revoke all keys for a user.
     */
    public static function revokeAll(int|string $userId): int
    {
        self::ensureTable();
        return Database::update(
            "UPDATE api_keys SET revoked = 1 WHERE user_id = ?",
            [$userId]
        );
    }

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------

    public static function forUser(int|string $userId): array
    {
        self::ensureTable();
        return Database::view(
            "SELECT id, name, key_prefix, scopes, expires_at, last_used_at, revoked, created_at
             FROM api_keys WHERE user_id = ? ORDER BY created_at DESC",
            [$userId]
        );
    }

    // -------------------------------------------------------------------------
    // DB scaffold
    // -------------------------------------------------------------------------

    private static function ensureTable(): void
    {
        $driver = self::dbDriver();
        if ($driver === 'sqlite') {
            Database::unprepared("CREATE TABLE IF NOT EXISTS api_keys (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id      INTEGER NOT NULL,
                name         TEXT NOT NULL DEFAULT '',
                key_prefix   TEXT NOT NULL,
                key_hash     TEXT NOT NULL UNIQUE,
                scopes       TEXT NOT NULL DEFAULT '[\"*\"]',
                expires_at   DATETIME,
                last_used_at DATETIME,
                revoked      INTEGER NOT NULL DEFAULT 0,
                created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            Database::unprepared("CREATE TABLE IF NOT EXISTS api_keys (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                user_id      INT NOT NULL,
                name         VARCHAR(255) NOT NULL DEFAULT '',
                key_prefix   VARCHAR(16) NOT NULL,
                key_hash     VARCHAR(64) NOT NULL UNIQUE,
                scopes       JSON NOT NULL,
                expires_at   DATETIME,
                last_used_at DATETIME,
                revoked      TINYINT NOT NULL DEFAULT 0,
                created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=INNODB");
            Database::unprepared("CREATE INDEX IF NOT EXISTS idx_api_keys_user ON api_keys (user_id)");
            Database::unprepared("CREATE INDEX IF NOT EXISTS idx_api_keys_revoked ON api_keys (revoked)");
        }
    }

    private static function dbDriver(): string
    {
        try {
            $pdo = \Nemesis\Core\Database::getPdo();
            if ($pdo) {
                return strtolower((string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
            }
            return \Nemesis\Core\Database::getDriverName();
        } catch (\Throwable) {
            return 'sqlite';
        }
    }
}
