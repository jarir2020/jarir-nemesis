<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 16 — JWT: access/refresh tokens, blacklist, JTI | 2026-04-06

namespace Nemesis\Auth;

use Nemesis\Core\Database;

class JWT
{
    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    private static function secret(): string
    {
        $s = (string) (getenv('JWT_SECRET') ?: getenv('APP_KEY') ?: '');
        if (strlen($s) < 32) {
            throw new \RuntimeException('JWT_SECRET must be at least 32 characters.');
        }
        return $s;
    }

    private static function ttl(): int
    {
        return (int) (getenv('JWT_TTL') ?: 900); // 15 min default
    }

    private static function refreshTtl(): int
    {
        return (int) (getenv('JWT_REFRESH_TTL') ?: 604800); // 7 days default
    }

    // -------------------------------------------------------------------------
    // Encode / Decode
    // -------------------------------------------------------------------------

    /**
     * Create a signed JWT access token.
     */
    public static function encode(array $payload, int $expiresIn = 0): string
    {
        $expiresIn = $expiresIn ?: self::ttl();
        $now = time();

        $payload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $expiresIn,
            'jti' => bin2hex(random_bytes(16)),
        ]);

        $header  = self::b64(['typ' => 'JWT', 'alg' => 'HS256']);
        $body    = self::b64($payload);
        $sig     = self::sign("{$header}.{$body}");

        return "{$header}.{$body}.{$sig}";
    }

    /**
     * Decode and validate a JWT. Throws on any failure.
     */
    public static function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Malformed JWT.');
        }

        [$header, $body, $sig] = $parts;

        if (!hash_equals(self::sign("{$header}.{$body}"), $sig)) {
            throw new \RuntimeException('Invalid JWT signature.');
        }

        $payload = json_decode(self::b64Decode($body), true);

        if (!is_array($payload)) {
            throw new \RuntimeException('JWT payload is not valid JSON.');
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \RuntimeException('JWT has expired.');
        }

        if (isset($payload['jti']) && self::isBlacklisted($payload['jti'])) {
            throw new \RuntimeException('JWT has been revoked.');
        }

        return $payload;
    }

    /**
     * Return payload or null (no exception).
     */
    public static function verify(string $token): ?array
    {
        try {
            return self::decode($token);
        } catch (\Throwable) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Refresh token pair
    // -------------------------------------------------------------------------

    /**
     * Issue an access + refresh token pair.
     * Stores refresh token in jwt_refresh_tokens (auto-created).
     *
     * @return array{access: string, refresh: string, expires_in: int}
     */
    public static function issueTokenPair(array $payload): array
    {
        self::ensureRefreshTable();

        $access  = self::encode($payload);
        $refresh = bin2hex(random_bytes(40));

        $decoded = self::decode($access);

        Database::statement(
            "INSERT INTO jwt_refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)",
            [
                $payload['sub'] ?? ($payload['user_id'] ?? null),
                hash('sha256', $refresh),
                date('Y-m-d H:i:s', time() + self::refreshTtl()),
            ]
        );

        return [
            'access'     => $access,
            'refresh'    => $refresh,
            'expires_in' => self::ttl(),
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Exchange a valid refresh token for a new token pair.
     * Rotates the refresh token (old one is deleted).
     *
     * @return array{access: string, refresh: string, expires_in: int}
     */
    public static function refreshTokenPair(string $refreshToken, array $newPayload): array
    {
        self::ensureRefreshTable();

        $hash = hash('sha256', $refreshToken);
        $row  = Database::view(
            "SELECT * FROM jwt_refresh_tokens WHERE token_hash = ? AND expires_at > ? LIMIT 1",
            [$hash, date('Y-m-d H:i:s')]
        );

        if (empty($row)) {
            throw new \RuntimeException('Refresh token is invalid or expired.');
        }

        // Revoke old refresh token
        Database::statement("DELETE FROM jwt_refresh_tokens WHERE token_hash = ?", [$hash]);

        return self::issueTokenPair($newPayload);
    }

    /**
     * Revoke an access token by blacklisting its JTI.
     * Also removes any refresh tokens for the user.
     */
    public static function revoke(string $accessToken, bool $revokeRefreshTokens = true): void
    {
        try {
            $payload = self::decode($accessToken);
        } catch (\Throwable) {
            return; // already invalid
        }

        if (isset($payload['jti'])) {
            self::ensureBlacklistTable();
            Database::statement(
                "INSERT INTO jwt_blacklist (jti, expires_at) VALUES (?, ?)",
                [$payload['jti'], date('Y-m-d H:i:s', $payload['exp'] ?? time() + 60)]
            );
        }

        if ($revokeRefreshTokens && isset($payload['sub'])) {
            self::ensureRefreshTable();
            Database::statement(
                "DELETE FROM jwt_refresh_tokens WHERE user_id = ?",
                [$payload['sub']]
            );
        }
    }

    /**
     * Purge expired blacklist entries (run periodically via scheduler).
     */
    public static function purgeBlacklist(): int
    {
        self::ensureBlacklistTable();
        return Database::delete(
            "DELETE FROM jwt_blacklist WHERE expires_at < ?",
            [date('Y-m-d H:i:s')]
        );
    }

    // -------------------------------------------------------------------------
    // Blacklist
    // -------------------------------------------------------------------------

    private static function isBlacklisted(string $jti): bool
    {
        try {
            self::ensureBlacklistTable();
            $rows = Database::view(
                "SELECT 1 FROM jwt_blacklist WHERE jti = ? AND expires_at > ? LIMIT 1",
                [$jti, date('Y-m-d H:i:s')]
            );
            return !empty($rows);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function ensureBlacklistTable(): void
    {
        $driver = self::dbDriver();
        if ($driver === 'sqlite') {
            Database::unprepared("CREATE TABLE IF NOT EXISTS jwt_blacklist (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                jti       TEXT NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL
            )");
        } else {
            Database::unprepared("CREATE TABLE IF NOT EXISTS jwt_blacklist (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                jti        VARCHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL
            ) ENGINE=INNODB");
        }
    }

    private static function ensureRefreshTable(): void
    {
        $driver = self::dbDriver();
        if ($driver === 'sqlite') {
            Database::unprepared("CREATE TABLE IF NOT EXISTS jwt_refresh_tokens (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            Database::unprepared("CREATE TABLE IF NOT EXISTS jwt_refresh_tokens (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                user_id    INT,
                token_hash VARCHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=INNODB");
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function sign(string $data): string
    {
        return self::b64Encode(hash_hmac('sha256', $data, self::secret(), true));
    }

    private static function b64(array $data): string
    {
        return self::b64Encode((string) json_encode($data));
    }

    private static function b64Encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64Decode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
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
