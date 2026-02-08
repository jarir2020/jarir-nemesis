<?php
namespace Nemesis\Core;

/**
 * PluginManifest - Parse and validate plugin.json files
 */
class PluginManifest {
    protected $data;
    protected $path;

    public function __construct($manifestPath) {
        $this->path = $manifestPath;
        $this->load();
    }

    protected function load() {
        if (!file_exists($this->path)) {
            throw new \Exception("Plugin manifest not found: {$this->path}");
        }

        $content = file_get_contents($this->path);
        $this->data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in manifest: " . json_last_error_msg());
        }

        $this->validate();
    }

    protected function validate() {
        $required = ['name', 'version', 'entry'];
        
        foreach ($required as $field) {
            if (!isset($this->data[$field])) {
                throw new \Exception("Missing required field in manifest: {$field}");
            }
        }

        // Validate version format
        if (!preg_match('/^\d+\.\d+\.\d+$/', $this->data['version'])) {
            throw new \Exception("Invalid version format. Use semantic versioning (e.g., 1.0.0)");
        }
    }

    public function get($key, $default = null) {
        return $this->data[$key] ?? $default;
    }

    public function getName() {
        return $this->data['name'];
    }

    public function getVersion() {
        return $this->data['version'];
    }

    public function getEntry() {
        return $this->data['entry'];
    }

    public function getRequirements() {
        return $this->data['requires'] ?? [];
    }

    public function getAutoload() {
        return $this->data['autoload'] ?? [];
    }

    public function getPermissions() {
        return $this->data['permissions'] ?? [];
    }

    public function getDirectory() {
        return dirname($this->path);
    }

    public function checkCompatibility() {
        $requires = $this->getRequirements();

        // Check PHP version
        if (isset($requires['php'])) {
            $phpVersion = $requires['php'];
            if (!version_compare(PHP_VERSION, str_replace('>=', '', $phpVersion), '>=')) {
                throw new \Exception("Plugin requires PHP {$phpVersion}, current: " . PHP_VERSION);
            }
        }

        // Check Nemesis version (placeholder for now)
        if (isset($requires['nemesis'])) {
            // TODO: Implement framework version checking
        }

        return true;
    }

    public function toArray() {
        return $this->data;
    }
}
