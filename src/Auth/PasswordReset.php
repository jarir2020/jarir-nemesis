<?php
declare(strict_types=1);

namespace Nemesis\Auth;

use InvalidArgumentException;
use Nemesis\Core\Config;
use Nemesis\Core\Database;
use Nemesis\Services\Mailer;
use RuntimeException;

/**
 * Issue and consume one-time password reset tokens.
 *
 * The raw token is sent only in the reset link. The database stores its
 * SHA-256 digest, so a database compromise does not expose a usable link.
 */
class PasswordReset
{
    protected Mailer $mailer;
    protected string $table;
    protected int $expires;

    /**
     * @param Mailer|null $mailer Optional mailer, useful for application adapters and tests.
     * @param string|null $table Optional table override; identifiers are validated.
     * @param int|null $expires Expiry in minutes. Defaults to auth.passwords.expire.
     */
    public function __construct(?Mailer $mailer = null, ?string $table = null, ?int $expires = null)
    {
        $configuredTable = Config::get(
            'auth.passwords.table',
            getenv('PASSWORD_RESET_TABLE') ?: 'password_resets'
        );
        $configuredExpiry = Config::get(
            'auth.passwords.expire',
            getenv('PASSWORD_RESET_EXPIRE') ?: 60
        );

        $this->mailer = $mailer ?? new Mailer();
        $this->table = self::validateIdentifier(
            $table ?? (string) $configuredTable,
            'Password reset table'
        );
        $this->expires = self::resolveExpiry($expires ?? $configuredExpiry);
    }

    /**
     * Generate, persist, and email a new reset token.
     *
     * Replacing the old token and creating the new one are one transaction so
     * a user has at most one active token after a successful issuance.
     */
    public function sendResetLink($email): bool
    {
        $email = $this->normalizeEmail($email);
        if ($email === null) {
            return false;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = self::hashToken($token);

        Database::transaction(function () use ($email, $tokenHash): void {
            $this->deleteToken($email);

            Database::table($this->table)->insert([
                'email' => $email,
                'token' => $tokenHash,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        });

        $baseUrl = (string) Config::get(
            'APP_URL',
            Config::get('app.url', 'http://localhost')
        );
        $resetLink = rtrim($baseUrl, '/')
            . "/password/reset?token={$token}&email=" . urlencode($email);

        $subject = 'Reset Your Password';
        $body = "<h1>Password Reset</h1><p>Click the link below to reset your password:</p>"
            . "<a href='{$resetLink}'>{$resetLink}</a>";

        return $this->mailer->send($email, $subject, $body);
    }

    /**
     * Validate a token, update the password, and consume the token atomically.
     */
    public function reset($email, $token, $newPassword): bool
    {
        $email = $this->normalizeEmail($email);
        $token = is_string($token) ? trim($token) : '';
        if ($email === null || $token === '') {
            return false;
        }

        return (bool) Database::transaction(function () use ($email, $token, $newPassword): bool {
            // MySQL and PostgreSQL use FOR UPDATE inside this transaction;
            // SQLite relies on the transaction's write lock.
            $record = $this->findToken($email);
            if (!$record) {
                return false;
            }

            $createdAt = strtotime((string) ($record['created_at'] ?? ''));
            if ($createdAt === false || $createdAt + ($this->expires * 60) <= time()) {
                $this->deleteToken($email);
                return false;
            }

            $storedHash = (string) ($record['token'] ?? '');
            $providedHash = self::hashToken($token);
            if (!hash_equals($storedHash, $providedHash)) {
                return false;
            }

            // Compare-and-delete prevents a concurrent request from replaying
            // this token or deleting a newer token issued in the meantime.
            if ($this->deleteToken($email, $storedHash) !== 1) {
                return false;
            }

            $hashed = password_hash((string) $newPassword, PASSWORD_BCRYPT);
            if ($hashed === false) {
                throw new RuntimeException('Unable to hash the new password.');
            }

            Database::table('users')
                ->where('email', '=', $email)
                ->update(['password' => $hashed]);

            return true;
        });
    }

    protected function deleteToken(string $email, ?string $tokenHash = null): int
    {
        $query = Database::table($this->table)->where('email', '=', $email);
        if ($tokenHash !== null) {
            $query->where('token', '=', $tokenHash);
        }

        return $query->delete();
    }

    /** @return array<string, mixed>|null */
    protected function findToken(string $email): ?array
    {
        $driver = strtolower((string) Database::connection()->getAttribute(\PDO::ATTR_DRIVER_NAME));

        if (!in_array($driver, ['mysql', 'pgsql'], true)) {
            $record = Database::table($this->table)
                ->where('email', '=', $email)
                ->first();
            return is_array($record) ? $record : null;
        }

        // The table name is validated in the constructor before interpolation.
        $quote = $driver === 'pgsql' ? '"' : '`';
        $table = $quote . $this->table . $quote;
        $rows = Database::view(
            "SELECT * FROM {$table} WHERE email = :email LIMIT 1 FOR UPDATE",
            ['email' => $email]
        );

        return $rows[0] ?? null;
    }

    protected static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    protected static function validateIdentifier(string $identifier, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier)) {
            throw new InvalidArgumentException("{$label} must be a simple SQL identifier.");
        }

        return $identifier;
    }

    protected static function resolveExpiry(mixed $value): int
    {
        if (is_int($value)) {
            $minutes = $value;
        } elseif (is_string($value) && preg_match('/^[0-9]+$/D', $value)) {
            $minutes = (int) $value;
        } else {
            throw new InvalidArgumentException('Password reset expiry must be a positive integer in minutes.');
        }

        if ($minutes < 1) {
            throw new InvalidArgumentException('Password reset expiry must be a positive integer in minutes.');
        }

        return $minutes;
    }

    protected function normalizeEmail(mixed $email): ?string
    {
        if (!is_string($email)) {
            return null;
        }

        $email = trim($email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? null : $email;
    }
}
