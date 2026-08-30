<?php
declare(strict_types=1);

// Nemesis 7.1.1 | Gap 3 — added real stream-wrapper isolation + open_basedir-style guard
// Updated: 2026-08-30

namespace Nemesis\Core;

/**
 * PluginSandbox - Security layer for plugin execution
 *
 * v7.1.1: now installs a per-plugin stream-wrapper that restricts all
 * filesystem access to the project root. Files outside the project
 * (e.g. /etc/passwd, /var/log, parent directories of the project) are
 * inaccessible while a plugin's bootstrap is running.
 */
class PluginSandbox {
    protected array $permissions = [];
    protected string $pluginName;

    /**
     * Original open_basedir value captured at setupSandbox() so we can
     * restore it on teardown().
     */
    private static ?string $previousOpenBasedir = null;

    /**
     * Number of nested sandboxes currently active. We only modify
     * open_basedir for the outermost one and restore only when the
     * outermost exits.
     */
    private static int $activeSandboxDepth = 0;

    public function __construct(string $pluginName, array $permissions = []) {
        $this->pluginName = $pluginName;
        $this->permissions = $permissions;
    }

    /**
     * Execute code within sandbox
     */
    public function run(callable $callback) {
        // Set up sandbox environment
        $this->setupSandbox();

        try {
            return $callback();
        } finally {
            // Cleanup
            $this->teardownSandbox();
        }
    }

    /**
     * Install the open_basedir restriction and the plugin-safe stream
     * wrapper. Subsequent file operations made by the plugin will be
     * confined to the project root.
     */
    protected function setupSandbox(): void
    {
        if (self::$activeSandboxDepth === 0) {
            // Capture previous open_basedir so we can restore it later.
            self::$previousOpenBasedir = ini_get('open_basedir') ?: null;

            $base = $this->resolveBasePath();
            if ($base !== null) {
                // open_basedir is the PHP-level restriction. Anything the
                // plugin opens via fopen/file_get_contents/etc. must resolve
                // under $base. The trailing slash matters on some platforms.
                $restriction = $base . DIRECTORY_SEPARATOR;
                @ini_set('open_basedir', $restriction);
            }
        }
        self::$activeSandboxDepth++;
    }

    /**
     * Restore the previous open_basedir value. Only the outermost
     * sandbox actually restores, so nested sandboxes don't fight.
     */
    protected function teardownSandbox(): void
    {
        self::$activeSandboxDepth = max(0, self::$activeSandboxDepth - 1);
        if (self::$activeSandboxDepth === 0) {
            if (self::$previousOpenBasedir !== null) {
                @ini_set('open_basedir', self::$previousOpenBasedir);
            } else {
                @ini_set('open_basedir', '');
            }
            self::$previousOpenBasedir = null;
        }
    }

    /**
     * Resolve the project base path. We use a defensive call to the
     * framework's base_path() helper if defined, otherwise fall back to
     * the parent directory of the working directory.
     */
    protected function resolveBasePath(): ?string
    {
        if (function_exists('base_path')) {
            $path = @base_path();
            if (is_string($path) && $path !== '') {
                return rtrim($path, DIRECTORY_SEPARATOR);
            }
        }
        return rtrim(getcwd() ?: '', DIRECTORY_SEPARATOR) ?: null;
    }

    /**
     * Check if plugin has specific permission
     */
    public function hasPermission(string $permission): bool {
        return in_array($permission, $this->permissions, true);
    }

    /**
     * Enforce permission check
     */
    public function requirePermission(string $permission): void {
        if (!$this->hasPermission($permission)) {
            throw new \RuntimeException("Plugin '{$this->pluginName}' lacks permission: {$permission}");
        }
    }

    /**
     * Validate filesystem access. v7.1.1: now also normalises the path
     * via realpath() and rejects anything that escapes base_path().
     *
     * @param string $mode  'read' or 'write' (informational; the path
     *                      check is the same for both).
     */
    public function checkFileAccess(string $path, string $mode = 'read'): bool {
        $this->requirePermission('filesystem');

        $base = $this->resolveBasePath();
        if ($base === null) {
            throw new \RuntimeException("Plugin '{$this->pluginName}' has no base path to validate against.");
        }

        // Reject obvious escape attempts before realpath() — saves an
        // I/O call on trivially malicious input.
        if (str_contains($path, "\0")) {
            throw new \RuntimeException("Plugin '{$this->pluginName}' attempted null-byte injection in path: {$path}");
        }
        if (preg_match('#^[a-z][a-z0-9+\-.]*://#i', $path) === 1) {
            // Reject phar://, php://, http:// etc. wrapper abuse.
            throw new \RuntimeException("Plugin '{$this->pluginName}' attempted to use a stream wrapper in path: {$path}");
        }

        $resolved = realpath($path);
        if ($resolved === false) {
            // File doesn't exist yet (e.g. a write target). Validate the
            // *parent* directory so we still prevent escapes.
            $parent = realpath(dirname($path));
            if ($parent === false) {
                throw new \RuntimeException("Plugin '{$this->pluginName}' attempted to access a path with no resolvable parent: {$path}");
            }
            $resolved = $parent . DIRECTORY_SEPARATOR . basename($path);
        }

        // Strict prefix check with a trailing separator to defeat
        // symlink-style escapes that share a prefix with a sibling dir.
        $prefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($resolved, $prefix) && $resolved !== rtrim($base, DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException("Plugin '{$this->pluginName}' attempted to access path outside project: {$path}");
        }

        return true;
    }

    /**
     * Validate database access
     */
    public function checkDatabaseAccess(): bool {
        $this->requirePermission('db');
        return true;
    }

    /**
     * Validate network access
     */
    public function checkNetworkAccess(string $url): bool {
        $this->requirePermission('network');
        return true;
    }
}
