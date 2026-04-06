<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 16 — TOTP 2FA: QR URI, backup codes, DB integration | 2026-04-06

namespace Nemesis\Auth;

use Nemesis\Core\Database;

class Totp
{
    private const CHARS  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const DIGITS = 6;
    private const STEP   = 30;
    private const BACKUP_COUNT = 8;

    private string $secret;
    private string $issuer;
    private string $label;

    public function __construct(string $secret = '', string $issuer = '', string $label = '')
    {
        $this->secret = $secret ?: $this->generateSecret();
        $this->issuer = $issuer ?: (string) (getenv('APP_NAME') ?: 'Nemesis');
        $this->label  = $label;
    }

    // -------------------------------------------------------------------------
    // Secret
    // -------------------------------------------------------------------------

    public function generateSecret(int $length = 16): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::CHARS[random_int(0, strlen(self::CHARS) - 1)];
        }
        return $secret;
    }

    public function getSecret(): string { return $this->secret; }

    // -------------------------------------------------------------------------
    // Provisioning URI (for QR code)
    // -------------------------------------------------------------------------

    public function getProvisioningUri(): string
    {
        $label  = rawurlencode("{$this->issuer}:{$this->label}");
        $issuer = rawurlencode($this->issuer);
        return "otpauth://totp/{$label}?secret={$this->secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    // -------------------------------------------------------------------------
    // Code generation & verification
    // -------------------------------------------------------------------------

    public function now(): string
    {
        return $this->generateCode((int) floor(time() / self::STEP));
    }

    /**
     * Verify a TOTP code. Allows ±$window steps (default 1 = ±30 seconds).
     */
    public function verify(string $code, int $window = 1): bool
    {
        $timestamp = (int) floor(time() / self::STEP);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->generateCode($timestamp + $i), str_pad($code, self::DIGITS, '0', STR_PAD_LEFT))) {
                return true;
            }
        }
        return false;
    }

    private function generateCode(int $timestamp): string
    {
        $time = pack('J', $timestamp); // 64-bit big-endian
        $hash = hash_hmac('sha1', $time, $this->base32Decode($this->secret), true);
        $offset = ord($hash[19]) & 0x0f;
        $code = (
            ((ord($hash[$offset])     & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8)  |
             (ord($hash[$offset + 3]) & 0xff)
        ) % (10 ** self::DIGITS);
        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $map    = array_flip(str_split(self::CHARS));
        $secret = strtoupper($secret);
        $output = '';
        $buffer = 0;
        $bufLen = 0;
        foreach (str_split($secret) as $char) {
            if (!isset($map[$char])) continue;
            $buffer = ($buffer << 5) | $map[$char];
            $bufLen += 5;
            if ($bufLen >= 8) {
                $bufLen -= 8;
                $output .= chr(($buffer >> $bufLen) & 0xff);
            }
        }
        return $output;
    }

    // -------------------------------------------------------------------------
    // Backup codes
    // -------------------------------------------------------------------------

    /**
     * Generate N one-time backup codes.
     * Returns plain-text codes (show once to user) and stores hashed copies in DB.
     *
     * @return string[] plain-text codes, formatted as XXXX-XXXX
     */
    public static function generateBackupCodes(int|string $userId, int $count = self::BACKUP_COUNT): array
    {
        self::ensureBackupTable();

        // Revoke any existing codes for this user
        Database::statement("DELETE FROM totp_backup_codes WHERE user_id = ?", [$userId]);

        $plain = [];
        for ($i = 0; $i < $count; $i++) {
            $code  = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars
            $plain[] = substr($code, 0, 4) . '-' . substr($code, 4, 4);
            Database::statement(
                "INSERT INTO totp_backup_codes (user_id, code_hash, used) VALUES (?, ?, 0)",
                [$userId, password_hash($code, PASSWORD_BCRYPT)]
            );
        }

        return $plain;
    }

    /**
     * Verify and consume a backup code (one-time use).
     */
    public static function verifyBackupCode(int|string $userId, string $input): bool
    {
        self::ensureBackupTable();

        $clean = strtoupper(str_replace('-', '', $input));
        $rows  = Database::view(
            "SELECT id, code_hash FROM totp_backup_codes WHERE user_id = ? AND used = 0",
            [$userId]
        );

        foreach ($rows as $row) {
            if (password_verify($clean, $row['code_hash'])) {
                Database::statement(
                    "UPDATE totp_backup_codes SET used = 1, used_at = ? WHERE id = ?",
                    [date('Y-m-d H:i:s'), $row['id']]
                );
                return true;
            }
        }

        return false;
    }

    /**
     * How many unused backup codes remain for a user.
     */
    public static function remainingBackupCodes(int|string $userId): int
    {
        self::ensureBackupTable();
        $rows = Database::view(
            "SELECT COUNT(*) AS cnt FROM totp_backup_codes WHERE user_id = ? AND used = 0",
            [$userId]
        );
        return (int) ($rows[0]['cnt'] ?? 0);
    }

    // -------------------------------------------------------------------------
    // DB scaffold
    // -------------------------------------------------------------------------

    private static function ensureBackupTable(): void
    {
        $driver = self::dbDriver();
        if ($driver === 'sqlite') {
            Database::unprepared("CREATE TABLE IF NOT EXISTS totp_backup_codes (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id   INTEGER NOT NULL,
                code_hash TEXT NOT NULL,
                used      INTEGER NOT NULL DEFAULT 0,
                used_at   DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            Database::unprepared("CREATE TABLE IF NOT EXISTS totp_backup_codes (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                user_id    INT NOT NULL,
                code_hash  VARCHAR(255) NOT NULL,
                used       TINYINT NOT NULL DEFAULT 0,
                used_at    DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=INNODB");
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
