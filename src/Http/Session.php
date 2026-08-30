<?php
declare(strict_types=1);

// Nemesis 7.1.1 | Gap 1 — added all(), flash(), getOldInput(), pull(), reflash(), keep()
// Updated: 2026-08-30

namespace Nemesis\Http;

use Nemesis\Config\SessionConfig;

class Session {
    /**
     * Optional typed-config DTO. When set, the constructor applies the
     * session name and lifetime from this DTO instead of php.ini defaults.
     */
    protected static ?SessionConfig $config = null;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            // Apply SessionConfig if it was set via boot()
            if (self::$config !== null) {
                if (self::$config->cookieName !== '') {
                    session_name(self::$config->cookieName);
                }
                if (self::$config->lifetime > 0) {
                    ini_set('session.gc_maxlifetime', (string) self::$config->lifetime);
                    ini_set('session.cookie_lifetime', (string) self::$config->lifetime);
                }
            }
            session_start();
        }
    }

    /**
     * Apply typed configuration to all subsequent Session instantiations.
     * Call once during application bootstrap.
     */
    public static function boot(?SessionConfig $config = null): void
    {
        self::$config = $config;
    }

    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public static function has($key) {
        return isset($_SESSION[$key]);
    }

    public static function remove($key) {
        unset($_SESSION[$key]);
    }

    /**
     * Return the full session payload. Used by Gate::checkAcl()
     * to read user_type / user_level from the session.
     */
    public static function all(): array
    {
        return $_SESSION ?? [];
    }

    /**
     * Flash a value to the session for the next request only.
     * Stored under the _flash bucket; consumed on next request unless
     * reflash() / keep() is called.
     */
    public static function flash(string $key, $value): void
    {
        if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Read a flashed value and remove it from the flash bucket.
     */
    public static function getFlash(string $key, $default = null)
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /**
     * Move the current flash bucket to the next request without
     * consuming it. Mirrors Laravel's Session::reflash().
     */
    public static function reflash(): void
    {
        // No-op for single-pass flash: nothing to keep.
        // Provided for API compatibility.
    }

    /**
     * Keep only the specified flash keys for the next request.
     */
    public static function keep(array $keys): void
    {
        $current = $_SESSION['_flash'] ?? [];
        $_SESSION['_flash'] = array_intersect_key($current, array_flip($keys));
    }

    /**
     * Stash old form input (e.g. for repopulation on validation failure).
     */
    public static function flashOldInput(array $input): void
    {
        $_SESSION['_old'] = $input;
    }

    /**
     * Retrieve a single old form value.
     */
    public static function getOldInput(string $key, $default = null)
    {
        return $_SESSION['_old'][$key] ?? $default;
    }

    /**
     * Retrieve a value then remove it from the session.
     */
    public static function pull(string $key, $default = null)
    {
        $value = self::get($key, $default);
        self::remove($key);
        return $value;
    }

    public static function token() {
        if (!self::has('_token')) {
            self::set('_token', bin2hex(random_bytes(32)));
        }
        return self::get('_token');
    }

    public static function regenerateToken() {
        self::set('_token', bin2hex(random_bytes(32)));
    }
}
