<?php
declare(strict_types=1);
namespace Nemesis\View;

/**
 * Registry for custom Blade-compatible directives.
 *
 * Usage:
 *   DirectiveRegistry::register('datetime', function (string $args): string {
 *       return "<?php echo date('Y-m-d H:i', {$args}); ?>";
 *   });
 *
 *   Then in a view: @datetime($timestamp)
 *
 * Phase 11 | Added: 2026-04-03
 */
class DirectiveRegistry
{
    /** @var array<string, callable(string): string> */
    private static array $directives = [];

    /**
     * Register a custom @directive.
     *
     * @param string   $name     Directive name without the @ (case-insensitive)
     * @param callable $handler  Receives the full args string (e.g. "('value')" or ""),
     *                           must return a PHP code string.
     */
    public static function register(string $name, callable $handler): void
    {
        self::$directives[strtolower($name)] = $handler;
    }

    /**
     * Check whether a custom directive is registered.
     */
    public static function has(string $name): bool
    {
        return isset(self::$directives[strtolower($name)]);
    }

    /**
     * Invoke a registered directive handler.
     *
     * @param string $name  Directive name (without @)
     * @param string $args  Full args string including parens, e.g. "('foo')" or ""
     * @return string       Compiled PHP code string
     */
    public static function call(string $name, string $args): string
    {
        return (self::$directives[strtolower($name)])($args);
    }

    /**
     * Return all registered directive names.
     *
     * @return string[]
     */
    public static function names(): array
    {
        return array_keys(self::$directives);
    }

    /**
     * Remove a directive (useful in tests).
     */
    public static function forget(string $name): void
    {
        unset(self::$directives[strtolower($name)]);
    }
}
