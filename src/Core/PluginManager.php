<?php
namespace Nemesis\Core;

/**
 * PluginManager - Central plugin management system
 */
class PluginManager {
    protected static $instance = null;
    protected $plugins = [];
    protected $active = [];
    protected $statePath;

    protected function __construct() {
        $this->statePath = base_path('storage/plugins.json');
        $this->loadState();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Discover and load all plugins
     */
    public function discover() {
        $pluginDirs = glob(base_path('plugins/*'), GLOB_ONLYDIR);

        foreach ($pluginDirs as $dir) {
            $manifestPath = $dir . '/plugin.json';
            
            if (file_exists($manifestPath)) {
                try {
                    $this->load($manifestPath);
                } catch (\Exception $e) {
                    // Log error but continue loading other plugins
                    error_log("Plugin load error: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Load a single plugin
     */
    public function load($manifestPath) {
        $manifest = new PluginManifest($manifestPath);
        $name = $manifest->getName();

        // Store plugin info
        $this->plugins[$name] = [
            'manifest' => $manifest,
            'loaded' => false
        ];

        // Only bootstrap if active
        if ($this->isActive($name)) {
            $this->bootstrap($name);
        }
    }

    /**
     * Bootstrap a plugin
     */
    protected function bootstrap($name) {
        if (!isset($this->plugins[$name])) {
            throw new \Exception("Plugin not found: {$name}");
        }

        $plugin = $this->plugins[$name];
        $manifest = $plugin['manifest'];

        // Check compatibility
        $manifest->checkCompatibility();

        // Register autoloader
        $this->registerAutoloader($manifest);

        // Create sandbox
        $sandbox = new PluginSandbox($name, $manifest->getPermissions());

        // Execute bootstrap file
        $bootstrapPath = $manifest->getDirectory() . '/' . $manifest->getEntry();
        
        if (!file_exists($bootstrapPath)) {
            throw new \Exception("Plugin bootstrap file not found: {$bootstrapPath}");
        }

        // Run in sandbox
        $sandbox->run(function() use ($bootstrapPath) {
            require $bootstrapPath;
        });

        $this->plugins[$name]['loaded'] = true;

        // Fire hook
        Plugin::fire('plugin.loaded', $name);
    }

    /**
     * Register plugin autoloader
     */
    protected function registerAutoloader($manifest) {
        $autoload = $manifest->getAutoload();
        
        if (isset($autoload['psr-4'])) {
            $loader = new \Composer\Autoload\ClassLoader();
            
            foreach ($autoload['psr-4'] as $namespace => $path) {
                $fullPath = $manifest->getDirectory() . '/' . $path;
                $loader->addPsr4($namespace, $fullPath);
            }
            
            $loader->register(true);
        }
    }

    /**
     * Enable a plugin
     */
    public function enable($name) {
        if (!isset($this->plugins[$name])) {
            throw new \Exception("Plugin not found: {$name}");
        }

        if (!in_array($name, $this->active)) {
            $this->active[] = $name;
            $this->saveState();
            
            // Bootstrap if not already loaded
            if (!$this->plugins[$name]['loaded']) {
                $this->bootstrap($name);
            }
        }

        return true;
    }

    /**
     * Disable a plugin
     */
    public function disable($name) {
        $key = array_search($name, $this->active);
        
        if ($key !== false) {
            unset($this->active[$key]);
            $this->active = array_values($this->active);
            $this->saveState();
        }

        return true;
    }

    /**
     * Check if plugin is active
     */
    public function isActive($name) {
        return in_array($name, $this->active);
    }

    /**
     * Get all plugins
     */
    public function getAll() {
        return $this->plugins;
    }

    /**
     * Get active plugins
     */
    public function getActive() {
        return array_filter($this->plugins, function($plugin, $name) {
            return $this->isActive($name);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Load activation state
     */
    protected function loadState() {
        if (file_exists($this->statePath)) {
            $data = json_decode(file_get_contents($this->statePath), true);
            $this->active = $data['active'] ?? [];
        }
    }

    /**
     * Save activation state
     */
    protected function saveState() {
        $dir = dirname($this->statePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'active' => $this->active,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        file_put_contents($this->statePath, json_encode($data, JSON_PRETTY_PRINT));
    }
}
