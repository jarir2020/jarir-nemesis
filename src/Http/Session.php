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
        // PHP reports PHP_SESSION_DISABLED when php.ini has no default
        // session.save_path. Configure the project-local path before trying
        // session_start() so that state can still be enabled for this request.
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            // Apply SessionConfig if it was set via boot()
            $config = self::$config ?? SessionConfig::fromEnv();
            $settings = function_exists('config') ? (array) \config('session', []) : [];
            $lifetimeSeconds = max(0, $config->lifetime * 60);

            $savePath = $config->path !== ''
                ? $config->path
                : (string) ($settings['path'] ?? $settings['save_path'] ?? '');
            if (
                $savePath !== ''
                && function_exists('base_path')
                && !str_starts_with($savePath, DIRECTORY_SEPARATOR)
                && preg_match('/^[A-Za-z]:[\\\\\/]/', $savePath) !== 1
            ) {
                $savePath = base_path($savePath);
            }
            if ($savePath !== '') {
                if (!is_dir($savePath) && !mkdir($savePath, 0755, true) && !is_dir($savePath)) {
                    throw new \RuntimeException("Unable to create session directory: {$savePath}");
                }
                if (session_save_path($savePath) === false) {
                    throw new \RuntimeException("Unable to set session save path: {$savePath}");
                }
            }

            if ($config->cookieName !== '') {
                session_name($config->cookieName);
            }
            if ($lifetimeSeconds > 0) {
                ini_set('session.gc_maxlifetime', (string) $lifetimeSeconds);
                ini_set('session.cookie_lifetime', (string) $lifetimeSeconds);
            }

            $sameSite = strtolower($config->sameSite);
            if (!in_array($sameSite, ['lax', 'strict', 'none'], true)) $sameSite = 'lax';
            $secure = $config->secure || $sameSite === 'none';
            session_set_cookie_params([
                'lifetime' => ($settings['expire_on_close'] ?? false) ? 0 : $lifetimeSeconds,
                'path'     => (string) ($settings['cookie_path'] ?? '/'),
                'domain'   => (string) ($settings['domain'] ?? ''),
                'secure'   => $secure,
                'httponly' => (bool) ($settings['http_only'] ?? true),
                'samesite' => $sameSite,
            ]);

            session_start();
            self::ageFlashData();
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
        return array_key_exists($key, $_SESSION ?? []);
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
        $_SESSION['_flash_new'][$key] = true;
    }

    /**
     * Read a flashed value and remove it from the flash bucket.
     */
    public static function getFlash(string $key, $default = null)
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        unset($_SESSION['_flash_new'][$key]);
        return $value;
    }

    /**
     * Move the current flash bucket to the next request without
     * consuming it. Mirrors Laravel's Session::reflash().
     */
    public static function reflash(): void
    {
        $flash = $_SESSION['_flash'] ?? [];
        $_SESSION['_flash_new'] = is_array($flash)
            ? array_fill_keys(array_keys($flash), true)
            : [];
    }

    /**
     * Keep only the specified flash keys for the next request.
     */
    public static function keep(array $keys): void
    {
        $current = $_SESSION['_flash'] ?? [];
        $kept = array_intersect_key($current, array_flip($keys));
        $_SESSION['_flash'] = $kept;
        $_SESSION['_flash_new'] = array_fill_keys(array_keys($kept), true);
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

    /** Age flash values at request start; values marked new survive one more request. */
    protected static function ageFlashData(): void
    {
        $flash = $_SESSION['_flash'] ?? [];
        if (!is_array($flash)) {
            unset($_SESSION['_flash'], $_SESSION['_flash_new']);
            return;
        }

        $new = $_SESSION['_flash_new'] ?? [];
        $new = is_array($new) ? $new : [];
        foreach (array_keys($flash) as $key) {
            if (!array_key_exists($key, $new)) {
                unset($_SESSION['_flash'][$key]);
            }
        }
        unset($_SESSION['_flash_new']);
    }
}
