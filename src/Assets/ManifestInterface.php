<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 10 — Asset Pipeline | Added: 2026-04-03

namespace Nemesis\Assets;

/**
 * Contract for build-tool manifest readers (Vite, Webpack Mix, etc.)
 */
interface ManifestInterface
{
    /**
     * Resolve a source asset path to its versioned public URL.
     *
     * @param  string $path  Source path relative to resources/ e.g. "js/app.js"
     * @return string        Full public URL e.g. "/build/assets/app-3a7b92f.js"
     */
    public function url(string $path): string;

    /**
     * Return true if the manifest file exists and was loaded successfully.
     */
    public function loaded(): bool;

    /**
     * Return all entries in the manifest as [sourcePath => publicUrl].
     *
     * @return array<string, string>
     */
    public function all(): array;
}
