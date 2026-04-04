<?php
declare(strict_types=1);
namespace Nemesis\Core;

use Nemesis\View\Engine;

/**
 * Nemesis View facade.
 *
 * Thin static wrapper around Engine — all real work is delegated there.
 * Maintains backwards-compatibility with existing addNamespace() + render()
 * call sites while transparently using the new Blade-compatible Engine.
 *
 * Phase 11 | Rewritten: 2026-04-03  (previous: plain require-based renderer)
 */
class View
{
    // ─────────────────────────────────────────────────────────────────────────
    // Configuration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Add a named view namespace.
     *
     * @param string $namespace  Short alias, e.g. 'admin'
     * @param string $path       Absolute directory path
     */
    public static function addNamespace(string $namespace, string $path): void
    {
        self::engine()->addNamespace($namespace, $path);
    }

    /**
     * Add an extra view search path.
     *
     * @param string $path  Absolute directory path
     */
    public static function addPath(string $path): void
    {
        self::engine()->addPath($path);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Rendering
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Render a view and echo the result.
     * Backwards-compatible with the original View::render() signature.
     *
     * @param string $view  Dot-notation view name or namespace::view
     * @param array  $data  Variables to expose in the view
     *
     * @throws \RuntimeException if the view file cannot be found
     */
    public static function render(string $view, array $data = []): void
    {
        echo self::engine()->render($view, $data);
    }

    /**
     * Compile and render a view, returning the HTML as a string.
     *
     * @param string $view
     * @param array  $data
     * @return string
     */
    public static function make(string $view, array $data = []): string
    {
        return self::engine()->render($view, $data);
    }

    /**
     * Check whether a view file exists.
     *
     * @param string $view
     * @return bool
     */
    public static function exists(string $view): bool
    {
        return self::engine()->exists($view);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Engine access
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return the shared Engine singleton.
     * Callers can reach the Engine directly for advanced configuration.
     */
    public static function engine(): Engine
    {
        return Engine::getInstance();
    }
}
