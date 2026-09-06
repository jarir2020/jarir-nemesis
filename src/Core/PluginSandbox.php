<?php
declare(strict_types=1);

// Nemesis 7.1.4 | Plugin filesystem path validation
// Updated: 2026-09-06

namespace Nemesis\Core;

/**
 * PluginSandbox - Security layer for plugin execution
 *
 * Plugin filesystem access is explicitly validated through checkFileAccess().
 * The sandbox does not mutate request-wide PHP settings such as open_basedir;
 * PHP cannot reliably relax a tightened open_basedir value later in the same
 * request.
 */
class PluginSandbox {
    protected array $permissions = [];
    protected string $pluginName;

    public function __construct(string $pluginName, array $permissions = []) {
        $this->pluginName = $pluginName;
        $this->permissions = $permissions;
    }

    /**
     * Execute code within sandbox
     */
    public function run(callable $callback) {
        return $callback();
    }

    /**
     * Resolve and canonicalize the project base path before prefix checks.
     */
    protected function resolveBasePath(): ?string
    {
        $path = null;
        if (function_exists('base_path')) {
            $candidate = @base_path();
            $path = is_string($candidate) && $candidate !== '' ? $candidate : null;
        }
        if ($path === null) {
            $path = getcwd() ?: null;
        }

        $resolved = $path !== null ? realpath($path) : false;
        return $resolved === false ? null : rtrim($resolved, DIRECTORY_SEPARATOR);
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
