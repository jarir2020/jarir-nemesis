<?php
declare(strict_types=1);
namespace Nemesis\View;

use Nemesis\Frontend\FrontendManager;

/**
 * Nemesis Template Engine.
 *
 * Responsibilities:
 *   - Resolve view names (dot notation, namespace::view, .blade.php / .php)
 *   - Compile templates to PHP and cache in storage/views/ (mtime-invalidated)
 *   - Render compiled views in an isolated scope with injected data
 *   - Manage Blade-style layout inheritance (@extends / @section / @yield)
 *   - Expose $__engine to compiled views for section callbacks
 *
 * Phase 11 | Added: 2026-04-03
 */
class Engine
{
    private static ?Engine $instance = null;

    private Compiler $compiler;

    /** @var string[] Default view search paths (checked in order). */
    private array $paths = [];

    /** @var array<string, string> namespace → base path */
    private array $namespaces = [];

    /** Directory where compiled PHP files are cached. */
    private string $cacheDir;

    // ── Per-render state ──────────────────────────────────────────────────────

    /** @var array<string, string> section name → captured HTML */
    private array $sections = [];

    /** Section currently being captured via ob_start(), or null. */
    private ?string $capturingSection = null;

    /** Layout name set by @extends in the current child view, or null. */
    private ?string $layout = null;

    // ─────────────────────────────────────────────────────────────────────────
    // Construction / singleton
    // ─────────────────────────────────────────────────────────────────────────

    public function __construct(array $paths = [], string $cacheDir = '')
    {
        $this->compiler = new Compiler();
        $this->paths    = array_map(fn($p) => rtrim($p, '/\\'), $paths);
        $this->cacheDir = $cacheDir ?: (dirname(__DIR__, 2) . '/storage/views');
    }

    public static function getInstance(): static
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Configuration
    // ─────────────────────────────────────────────────────────────────────────

    public function addPath(string $path): void
    {
        $this->paths[] = rtrim($path, '/\\');
    }

    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] = rtrim($path, '/\\');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // View resolution
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve a view name to an absolute file path.
     *
     * Resolution order:
     *   1. namespace::dot.notation  →  namespace_path/dot/notation.blade.php|.php
     *   2. dot.notation             →  each $this->paths / dot/notation.blade.php|.php
     *   3. Fallback                 →  project_root/app/Views/dot/notation.blade.php|.php
     *
     * @throws \RuntimeException if no matching file found.
     */
    public function findView(string $view): string
    {
        $extensions = ['.blade.php', '.php'];
        $frameworkPath = FrontendManager::getInstance()->currentViewPath();

        // Namespaced: foo::bar.baz
        if (str_contains($view, '::')) {
            [$ns, $name] = explode('::', $view, 2);
            if (isset($this->namespaces[$ns])) {
                $rel = str_replace('.', '/', $name);
                foreach ($extensions as $ext) {
                    $path = $this->namespaces[$ns] . '/' . $rel . $ext;
                    if (file_exists($path)) return $path;
                }
            }
        }

        $rel = str_replace('.', '/', $view);

        // Frontend-specific view directory for the active framework.
        if ($frameworkPath !== null) {
            foreach ($extensions as $ext) {
                $path = $frameworkPath . '/' . $rel . $ext;
                if (file_exists($path)) return $path;
            }
        }

        // Registered paths
        foreach ($this->paths as $base) {
            foreach ($extensions as $ext) {
                $path = $base . '/' . $rel . $ext;
                if (file_exists($path)) return $path;
            }
        }

        // Legacy fallback: app/Views/
        $root = dirname(__DIR__, 2);
        foreach ($extensions as $ext) {
            $path = $root . '/app/Views/' . $rel . $ext;
            if (file_exists($path)) return $path;
        }

        throw new \RuntimeException("View [{$view}] not found.");
    }

    /**
     * Check whether a view file can be resolved (no exception thrown).
     */
    public function exists(string $view): bool
    {
        try {
            $this->findView($view);
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Compilation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Compile a view source file and return the path to the cached PHP file.
     * The cached file is invalidated whenever the source file is newer.
     */
    public function compile(string $viewPath): string
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        $hash      = md5(realpath($viewPath) ?: $viewPath);
        $cachePath = $this->cacheDir . '/' . $hash . '.php';

        if (!file_exists($cachePath) || filemtime($viewPath) > filemtime($cachePath)) {
            $source   = file_get_contents($viewPath);
            $compiled = $this->compiler->compile($source);
            file_put_contents($cachePath, $compiled, LOCK_EX);
        }

        return $cachePath;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Rendering
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Render a view and return the HTML string.
     *
     * Layout inheritance:
     *   If the view calls @extends('layout'), the child view is executed first
     *   to capture sections, then the parent layout is rendered with those
     *   sections available for @yield calls.
     *
     * @param string  $view  View name (dot notation or namespace::view)
     * @param array   $data  Variables to expose in the view
     * @return string        Rendered HTML
     *
     * @throws \RuntimeException if the view file cannot be found
     */
    public function render(string $view, array $data = []): string
    {
        $viewPath = $this->findView($view);
        $compiled = $this->compile($viewPath);

        // Save outer render state so nested @include calls don't break it
        $savedLayout    = $this->layout;
        $savedCapturing = $this->capturingSection;
        $this->layout           = null;
        $this->capturingSection = null;

        // Execute compiled view (sections are captured into $this->sections)
        $html = $this->evaluate($compiled, $data);

        // Capture the layout name before restoring outer state
        $layout = $this->layout;

        // Restore outer state
        $this->layout           = $savedLayout;
        $this->capturingSection = $savedCapturing;

        // If @extends was used, discard child output and render parent instead.
        // Sections captured during child execution remain in $this->sections
        // and are available for @yield calls in the parent layout.
        if ($layout !== null) {
            $html = $this->render($layout, $data);
        }

        return $html;
    }

    /**
     * Execute a compiled PHP file in an isolated scope and return its output.
     *
     * Variables available inside the view:
     *   $__engine  — this Engine instance (used by compiled directives)
     *   $__data    — the full data array (used by @include / @component)
     *   + all keys from $data extracted as individual variables
     */
    private function evaluate(string $__path, array $__data): string
    {
        ob_start();
        try {
            // Static closure limits variable leakage; $__path/$__data/$__engine
            // are the only Engine-internal names injected into the view scope.
            (static function (string $__path, array $__data, Engine $__engine): void {
                extract($__data, EXTR_SKIP);
                require $__path;
            })($__path, $__data, $this);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Section management  (called from compiled view code)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Begin capturing a named section.
     * Called by the compiled @section('name') directive.
     * Expects ob_start() to be called immediately after in the compiled output.
     */
    public function startSection(string $name): void
    {
        $this->capturingSection = $name;
    }

    /**
     * End the active section capture and store the buffered content.
     * Called by the compiled @endsection / @stop directive.
     */
    public function endSection(): void
    {
        $name = $this->capturingSection;
        if ($name === null) return;

        $this->sections[$name] = (string) ob_get_clean();
        $this->capturingSection = null;
    }

    /**
     * Set a section's content directly (used by @section('name', 'value')).
     * Does NOT overwrite if a child view already defined this section.
     */
    public function setSection(string $name, string $content): void
    {
        // Child sections win; only set if not yet defined
        if (!isset($this->sections[$name])) {
            $this->sections[$name] = $content;
        }
    }

    /**
     * Return a section's content, or $default if not defined.
     * Called by the compiled @yield directive.
     */
    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Check whether a named section has content (used by @hasSection).
     */
    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]) && $this->sections[$name] !== '';
    }

    /**
     * Return the current parent section content (used by @parent inside @section).
     */
    public function yieldParentSection(): string
    {
        return $this->capturingSection
            ? ($this->sections[$this->capturingSection] ?? '')
            : '';
    }

    /**
     * Set the layout for the current render pass.
     * Called by the compiled @extends directive.
     */
    public function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Testing helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** Reset all runtime state (useful between tests). */
    public function resetState(): void
    {
        $this->sections        = [];
        $this->capturingSection = null;
        $this->layout          = null;
    }

    /** Expose the raw sections array for assertions. */
    public function getSections(): array
    {
        return $this->sections;
    }
}
