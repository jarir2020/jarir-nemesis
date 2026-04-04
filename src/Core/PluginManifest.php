<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 7 — Plugin Manifest v2 | Updated: 2026-04-03
// v2 adds optional: provides, tags, conflicts (all backwards-compatible)

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

    // -------------------------------------------------------------------------
    // v2 fields (all optional — backwards-compatible with v1 manifests)
    // -------------------------------------------------------------------------

    /**
     * Service bindings this plugin registers.
     * e.g. ["CloudStorage\\StorageInterface" => "CloudStorage\\S3Driver"]
     *
     * @return array<string,string>
     */
    public function getProvides(): array
    {
        return $this->data['provides'] ?? [];
    }

    /**
     * Categorisation tags.
     * e.g. ["storage", "cloud"]
     *
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->data['tags'] ?? [];
    }

    /**
     * Plugin names this plugin is incompatible with.
     * e.g. ["OtherStoragePlugin"]
     *
     * @return list<string>
     */
    public function getConflicts(): array
    {
        return $this->data['conflicts'] ?? [];
    }

    /**
     * Returns true if this is a v2 manifest (has at least one v2 field).
     */
    public function isV2(): bool
    {
        return isset($this->data['provides'])
            || isset($this->data['tags'])
            || isset($this->data['conflicts']);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
