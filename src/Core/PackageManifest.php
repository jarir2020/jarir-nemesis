<?php
declare(strict_types=1);

// Nemesis | Package auto-discovery (Laravel-style extra.nemesis)

namespace Nemesis\Core;

/**
 * Discovers framework service providers shipped by installed Composer packages.
 *
 * Any package can opt in by declaring, in its own composer.json:
 *
 *     "extra": { "nemesis": { "providers": ["Vendor\\Pkg\\NemesisServiceProvider"] } }
 *
 * This reads vendor/composer/installed.json (written by Composer on every
 * install/update), collects those providers, and caches the result to
 * storage/framework/packages.php so subsequent requests skip the scan. The cache
 * auto-rebuilds whenever installed.json is newer (i.e. after composer changes).
 *
 * Providers are merely *listed* here; index.php instantiates them and calls
 * register()/boot(). Because providers register lazy container bindings, an
 * installed-but-unused package stays dormant — exactly like Laravel.
 */
class PackageManifest
{
    protected string $installedJson;

    protected string $cacheFile;

    public function __construct(string $basePath)
    {
        $basePath = rtrim($basePath, '/\\');
        $this->installedJson = $basePath . '/vendor/composer/installed.json';
        $this->cacheFile     = $basePath . '/storage/framework/packages.php';
    }

    /**
     * Fully-qualified provider class names discovered across all packages.
     *
     * @return string[]
     */
    public function providers(): array
    {
        $providers = [];

        foreach ($this->manifest() as $package) {
            foreach (($package['providers'] ?? []) as $provider) {
                $providers[] = $provider;
            }
        }

        return array_values(array_unique($providers));
    }

    /**
     * @return array<string, array{providers?: string[], aliases?: array<string,string>}>
     */
    public function manifest(): array
    {
        if ($this->cacheIsFresh()) {
            $cached = @include $this->cacheFile;
            if (is_array($cached)) {
                return $cached;
            }
        }

        return $this->build();
    }

    protected function cacheIsFresh(): bool
    {
        if (! is_file($this->cacheFile)) {
            return false;
        }

        // Stale if installed.json changed after the cache was written.
        return ! is_file($this->installedJson)
            || filemtime($this->cacheFile) >= filemtime($this->installedJson);
    }

    /**
     * @return array<string, array{providers?: string[], aliases?: array<string,string>}>
     */
    protected function build(): array
    {
        $manifest = [];

        if (is_file($this->installedJson)) {
            $data = json_decode((string) file_get_contents($this->installedJson), true);
            // Composer 2 nests under "packages"; Composer 1 is a flat list.
            $packages = $data['packages'] ?? (is_array($data) ? $data : []);

            foreach ($packages as $package) {
                $nemesis = $package['extra']['nemesis'] ?? null;
                if (is_array($nemesis) && ! empty($nemesis)) {
                    $manifest[$package['name'] ?? uniqid('pkg_', true)] = $nemesis;
                }
            }
        }

        $this->writeCache($manifest);

        return $manifest;
    }

    protected function writeCache(array $manifest): void
    {
        $dir = dirname($this->cacheFile);

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (is_dir($dir) && (is_writable($dir) || (is_file($this->cacheFile) && is_writable($this->cacheFile)))) {
            @file_put_contents(
                $this->cacheFile,
                '<?php return ' . var_export($manifest, true) . ';' . PHP_EOL
            );
        }
    }

    /** Force a rebuild (e.g. from a `nemesis package:discover` command). */
    public function refresh(): array
    {
        if (is_file($this->cacheFile)) {
            @unlink($this->cacheFile);
        }

        return $this->build();
    }
}
