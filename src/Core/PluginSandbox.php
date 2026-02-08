<?php
namespace Nemesis\Core;

/**
 * PluginSandbox - Security layer for plugin execution
 */
class PluginSandbox {
    protected $permissions = [];
    protected $pluginName;

    public function __construct($pluginName, array $permissions = []) {
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

    protected function setupSandbox() {
        // Register permission checks
        // In production, this would use PHP's stream wrappers and error handlers
    }

    protected function teardownSandbox() {
        // Cleanup sandbox environment
    }

    /**
     * Check if plugin has specific permission
     */
    public function hasPermission($permission) {
        return in_array($permission, $this->permissions);
    }

    /**
     * Enforce permission check
     */
    public function requirePermission($permission) {
        if (!$this->hasPermission($permission)) {
            throw new \Exception("Plugin '{$this->pluginName}' lacks permission: {$permission}");
        }
    }

    /**
     * Validate filesystem access
     */
    public function checkFileAccess($path, $mode = 'read') {
        $this->requirePermission('filesystem');
        
        // Additional path validation
        $realPath = realpath($path);
        $basePath = base_path();

        // Prevent access outside project directory
        if ($realPath && strpos($realPath, $basePath) !== 0) {
            throw new \Exception("Plugin '{$this->pluginName}' attempted to access path outside project: {$path}");
        }

        return true;
    }

    /**
     * Validate database access
     */
    public function checkDatabaseAccess() {
        $this->requirePermission('db');
        return true;
    }

    /**
     * Validate network access
     */
    public function checkNetworkAccess($url) {
        $this->requirePermission('network');
        return true;
    }
}
