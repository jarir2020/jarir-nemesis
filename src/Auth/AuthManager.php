<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 16 — AuthManager: unified auth facade | 2026-04-06

namespace Nemesis\Auth;

use Nemesis\Http\Request;
use Nemesis\Core\Database;

/**
 * AuthManager — central authentication facade.
 *
 * Usage:
 *   $tokens = AuthManager::attempt(['email' => ..., 'password' => ...]);
 *   $user   = AuthManager::user($request);
 *   AuthManager::logout($request);
 */
class AuthManager
{
    private static ?array $resolvedUser = null;

    // -------------------------------------------------------------------------
    // Attempt login
    // -------------------------------------------------------------------------

    /**
     * Attempt credential login. Returns token pair on success, null on failure.
     *
     * @param array{email: string, password: string} $credentials
     * @param array $extraPayload  Extra claims to embed in the JWT
     */
    public static function attempt(array $credentials, array $extraPayload = []): ?array
    {
        $email    = $credentials['email']    ?? $credentials['username'] ?? '';
        $password = $credentials['password'] ?? '';

        if (!$email || !$password) return null;

        $rows = Database::view(
            "SELECT * FROM users WHERE email = ? LIMIT 1",
            [$email]
        );

        if (empty($rows)) return null;

        $user = $rows[0];

        if (!password_verify($password, $user['password'] ?? '')) return null;

        $payload = array_merge([
            'sub'   => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'] ?? 'user',
        ], $extraPayload);

        return JWT::issueTokenPair($payload);
    }

    // -------------------------------------------------------------------------
    // Resolve user from request
    // -------------------------------------------------------------------------

    /**
     * Resolve the authenticated user from a Bearer JWT or X-Api-Key header.
     * Returns the user array from DB, or null.
     */
    public static function user(Request $request): ?array
    {
        if (self::$resolvedUser !== null) {
            return self::$resolvedUser;
        }

        // 1. Try JWT Bearer token
        $bearer = $request->bearerToken();
        if ($bearer) {
            $payload = JWT::verify($bearer);
            if ($payload && isset($payload['sub'])) {
                self::$resolvedUser = self::findUser((int) $payload['sub']);
                return self::$resolvedUser;
            }
        }

        // 2. Try API key (X-Api-Key header)
        $apiKey = $request->header('X-Api-Key');
        if ($apiKey) {
            $keyRow = ApiKey::verify($apiKey);
            if ($keyRow && isset($keyRow['user_id'])) {
                self::$resolvedUser = self::findUser((int) $keyRow['user_id']);
                if (self::$resolvedUser) {
                    self::$resolvedUser['_api_key'] = $keyRow; // attach for scope checks
                }
                return self::$resolvedUser;
            }
        }

        return null;
    }

    /**
     * Return the JWT payload without hitting the DB (lightweight).
     */
    public static function payload(Request $request): ?array
    {
        $bearer = $request->bearerToken();
        return $bearer ? JWT::verify($bearer) : null;
    }

    /**
     * Check if the request is authenticated.
     */
    public static function check(Request $request): bool
    {
        return self::user($request) !== null;
    }

    /**
     * Get the authenticated user ID.
     */
    public static function id(Request $request): int|string|null
    {
        return self::user($request)['id'] ?? null;
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    /**
     * Revoke the current access token (and optionally all refresh tokens).
     */
    public static function logout(Request $request, bool $revokeAll = false): void
    {
        $bearer = $request->bearerToken();
        if ($bearer) {
            JWT::revoke($bearer, $revokeAll);
        }
        self::$resolvedUser = null;
    }

    // -------------------------------------------------------------------------
    // Refresh
    // -------------------------------------------------------------------------

    /**
     * Exchange a refresh token for a new token pair.
     * Pass the new payload (usually fetched fresh from DB).
     */
    public static function refresh(string $refreshToken, array $newPayload): array
    {
        return JWT::refreshTokenPair($refreshToken, $newPayload);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function findUser(int $id): ?array
    {
        try {
            $rows = Database::view("SELECT * FROM users WHERE id = ? LIMIT 1", [$id]);
            return $rows[0] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Reset resolved user (useful in tests). */
    public static function reset(): void
    {
        self::$resolvedUser = null;
    }
}
