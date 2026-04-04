<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 10 — Webpack Mix Manifest Reader | Added: 2026-04-03

namespace Nemesis\Assets;

/**
 * Reads Laravel Mix / Webpack's mix-manifest.json and resolves versioned URLs.
 *
 * Mix manifest structure:
 *   {
 *     "/js/app.js":  "/js/app.js?id=3a7b92f",
 *     "/css/app.css": "/css/app.css?id=1a2b3c4"
 *   }
 *
 * Keys and values are web-root-relative paths (start with /).
 */
class WebpackManifest implements ManifestInterface
{
    private array $manifest = [];
    private bool  $loaded   = false;

    public function __construct(
        private readonly string $manifestPath,
        private readonly string $publicPath = '/public',
    ) {
        $this->load();
    }

    public function loaded(): bool
    {
        return $this->loaded;
    }

    public function all(): array
    {
        return $this->manifest;
    }

    /**
     * Resolve a source path to its versioned public URL.
     *
     * Mix keys are web-root-relative, e.g. "/js/app.js".
     * Callers may pass with or without the leading slash.
     */
    public function url(string $path): string
    {
        // Normalise to /js/app.js style
        $key = '/' . ltrim($path, '/');

        if (isset($this->manifest[$key])) {
            return $this->manifest[$key];
        }

        // Fallback: return the path unchanged
        return $key;
    }

    /**
     * Generate HTML tags for a JS or CSS asset.
     */
    public function tags(string $path): string
    {
        $url = $this->url($path);
        $ext = strtolower(pathinfo(strtok($url, '?'), PATHINFO_EXTENSION));

        return match ($ext) {
            'css'  => '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES) . '">',
            'js'   => '<script src="' . htmlspecialchars($url, ENT_QUOTES) . '" defer></script>',
            default => '<!-- unknown asset type: ' . htmlspecialchars($url, ENT_QUOTES) . ' -->',
        };
    }

    // -------------------------------------------------------------------------

    private function load(): void
    {
        if (!file_exists($this->manifestPath)) {
            return;
        }

        $raw = file_get_contents($this->manifestPath);
        if ($raw === false) return;

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return;

        $this->manifest = $decoded;
        $this->loaded   = true;
    }
}
