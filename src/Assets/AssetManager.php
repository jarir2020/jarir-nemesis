<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 10 — Asset Manager | Added: 2026-04-03

namespace Nemesis\Assets;

/**
 * Central asset URL resolver.
 *
 * Picks the right manifest (Vite or Webpack) based on config/assets.php
 * and exposes a unified public API for templates and helpers.
 *
 * Usage:
 *   AssetManager::url('js/app.js')          → '/build/assets/app-3a7b92f.js'
 *   AssetManager::vite('resources/js/app.js') → Vite-specific URL
 *   AssetManager::mix('/js/app.js')          → Mix versioned URL
 *   AssetManager::tags('resources/js/app.js') → full <script>/<link> HTML
 */
class AssetManager
{
    private static ?self        $instance = null;
    private readonly string     $driver;
    private readonly string     $publicBase;
    private ?ManifestInterface  $manifest = null;
    private ?ViteManifest       $viteManifest = null;
    private ?WebpackManifest    $webpackManifest = null;

    private function __construct(array $config)
    {
        $this->driver     = $config['driver']  ?? 'vite';
        $this->publicBase = rtrim($config['public_base'] ?? '', '/');

        if ($this->driver === 'vite') {
            $viteCfg            = $config['vite'] ?? [];
            $this->viteManifest = new ViteManifest(
                manifestPath: $viteCfg['manifest'] ?? 'public/build/.vite/manifest.json',
                devUrl:       $viteCfg['dev_url']  ?? 'http://localhost:5173',
                hotFile:      $viteCfg['hot_file'] ?? 'storage/framework/vite.hot',
            );
            $this->manifest = $this->viteManifest;
        }

        if ($this->driver === 'webpack') {
            $wpCfg                 = $config['webpack'] ?? [];
            $this->webpackManifest = new WebpackManifest(
                manifestPath: $wpCfg['manifest'] ?? 'public/mix-manifest.json',
            );
            $this->manifest = $this->webpackManifest;
        }
    }

    // -------------------------------------------------------------------------
    // Singleton bootstrap
    // -------------------------------------------------------------------------

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            $config = [];
            if (function_exists('config')) {
                $config = config('assets', []);
            } elseif (file_exists(__DIR__ . '/../../config/assets.php')) {
                $config = require __DIR__ . '/../../config/assets.php';
            }
            self::$instance = new self(is_array($config) ? $config : []);
        }
        return self::$instance;
    }

    /** Reset singleton (for tests). */
    public static function flush(): void
    {
        self::$instance = null;
    }

    /** Bootstrap with a custom config array (for tests / early boot). */
    public static function boot(array $config): self
    {
        self::$instance = new self($config);
        return self::$instance;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Resolve any asset to a versioned public URL.
     *
     * Falls back to a plain /public/{path}?v={mtime} when no manifest is loaded.
     */
    public static function url(string $path): string
    {
        $mgr = self::getInstance();
        if ($mgr->manifest !== null) {
            return $mgr->manifest->url($path);
        }
        return $mgr->rawUrl($path);
    }

    /**
     * Generate <script> / <link> HTML tags for an entry point.
     * Works with both Vite (with HMR) and Webpack Mix.
     */
    public static function tags(string $path): string
    {
        $mgr = self::getInstance();

        if ($mgr->viteManifest !== null) {
            return $mgr->viteManifest->tags($path);
        }

        if ($mgr->webpackManifest !== null) {
            return $mgr->webpackManifest->tags($path);
        }

        // No manifest — emit a plain tag based on extension
        $url = $mgr->rawUrl($path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'css'  => '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES) . '">',
            'js'   => '<script src="' . htmlspecialchars($url, ENT_QUOTES) . '" defer></script>',
            default => '<!-- asset: ' . htmlspecialchars($url, ENT_QUOTES) . ' -->',
        };
    }

    /**
     * Vite-specific URL resolution (with HMR awareness).
     */
    public static function vite(string $path): string
    {
        $mgr = self::getInstance();
        return $mgr->viteManifest?->url($path) ?? self::url($path);
    }

    /**
     * Vite entry-point tags (with HMR @vite/client injection).
     */
    public static function viteTags(string $path): string
    {
        $mgr = self::getInstance();
        return $mgr->viteManifest?->tags($path) ?? self::tags($path);
    }

    /**
     * Webpack Mix URL resolution.
     */
    public static function mix(string $path): string
    {
        $mgr = self::getInstance();
        return $mgr->webpackManifest?->url($path) ?? self::url($path);
    }

    /**
     * Whether the current build driver has a loaded manifest.
     */
    public static function hasManifest(): bool
    {
        return self::getInstance()->manifest?->loaded() ?? false;
    }

    /**
     * Return the active driver name: 'vite', 'webpack', or 'none'.
     */
    public static function driver(): string
    {
        return self::getInstance()->driver;
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function rawUrl(string $path): string
    {
        $clean = '/' . ltrim($path, '/');

        // Try file mtime for cache busting
        $disk = getcwd() . '/public' . $clean;
        if (file_exists($disk)) {
            return $clean . '?v=' . filemtime($disk);
        }

        return $clean;
    }
}
