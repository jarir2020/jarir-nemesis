<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 12 — Core CMS | Created: 2026-04-03
// WordPress-style hook & filter system with priority ordering.

namespace Nemesis\Hooks;

/**
 * HookDispatcher — named hook & filter bus.
 *
 * Actions  (doAction)     : fire side-effects, no return value.
 * Filters  (applyFilters) : transform a value through a pipeline, return final value.
 *
 * Usage:
 *   // Register
 *   HookDispatcher::addAction('nemesis.boot', fn() => log('booted'), priority: 5);
 *   HookDispatcher::addFilter('post.title', fn(string $t) => strtoupper($t));
 *
 *   // Fire
 *   HookDispatcher::doAction('nemesis.boot');
 *   $title = HookDispatcher::applyFilters('post.title', $raw);
 *
 * Global helpers (defined in Helpers.php):
 *   addHook()     addFilter()   removeHook()   removeFilter()
 *   doHook()      applyFilters()
 */
class HookDispatcher
{
    /** @var array<string, array<int, list<callable>>> */
    private array $actions = [];

    /** @var array<string, array<int, list<callable>>> */
    private array $filters = [];

    /** Stack of currently executing hook names (supports nested hooks). */
    private array $currentHooks = [];

    private static ?self $instance = null;

    // -------------------------------------------------------------------------
    // Singleton
    // -------------------------------------------------------------------------

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /** Replace singleton — useful in tests. */
    public static function setInstance(?self $instance): void
    {
        static::$instance = $instance;
    }

    /** Reset all registered hooks and filters (test helper). */
    public static function reset(): void
    {
        $i = static::getInstance();
        $i->actions      = [];
        $i->filters      = [];
        $i->currentHooks = [];
    }

    // -------------------------------------------------------------------------
    // Action registration
    // -------------------------------------------------------------------------

    /**
     * Register a callback for a named action hook.
     *
     * @param  string   $hook      Named hook point, e.g. 'nemesis.boot'
     * @param  callable $callback  Called when the hook fires
     * @param  int      $priority  Lower fires first (default 10)
     */
    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        static::getInstance()->actions[$hook][$priority][] = $callback;
    }

    /**
     * Remove a specific action callback.
     * The callable must be the exact same reference used when registering.
     */
    public static function removeAction(string $hook, callable $callback, int $priority = 10): void
    {
        $d = static::getInstance();
        foreach ($d->actions[$hook][$priority] ?? [] as $k => $cb) {
            if ($cb === $callback) {
                unset($d->actions[$hook][$priority][$k]);
            }
        }
    }

    /** Check whether any callbacks are registered for an action hook. */
    public static function hasAction(string $hook, int $priority = null): bool
    {
        $d = static::getInstance();
        if (!isset($d->actions[$hook])) return false;
        if ($priority !== null) return !empty($d->actions[$hook][$priority]);
        foreach ($d->actions[$hook] as $callbacks) {
            if (!empty($callbacks)) return true;
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Filter registration
    // -------------------------------------------------------------------------

    /**
     * Register a callback for a named filter hook.
     *
     * @param  string   $hook      Named filter point, e.g. 'post.title'
     * @param  callable $callback  fn(mixed $value, ...$extra): mixed
     * @param  int      $priority  Lower runs first (default 10)
     */
    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        static::getInstance()->filters[$hook][$priority][] = $callback;
    }

    /**
     * Remove a specific filter callback.
     */
    public static function removeFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $d = static::getInstance();
        foreach ($d->filters[$hook][$priority] ?? [] as $k => $cb) {
            if ($cb === $callback) {
                unset($d->filters[$hook][$priority][$k]);
            }
        }
    }

    /** Check whether any callbacks are registered for a filter hook. */
    public static function hasFilter(string $hook, int $priority = null): bool
    {
        $d = static::getInstance();
        if (!isset($d->filters[$hook])) return false;
        if ($priority !== null) return !empty($d->filters[$hook][$priority]);
        foreach ($d->filters[$hook] as $callbacks) {
            if (!empty($callbacks)) return true;
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Firing
    // -------------------------------------------------------------------------

    /**
     * Fire an action hook — calls every registered callback in priority order.
     * Extra arguments are forwarded to each callback.
     */
    public static function doAction(string $hook, mixed ...$args): void
    {
        $d = static::getInstance();
        $d->currentHooks[] = $hook;

        $buckets = $d->actions[$hook] ?? [];
        ksort($buckets);
        foreach ($buckets as $callbacks) {
            foreach ($callbacks as $cb) {
                $cb(...$args);
            }
        }

        array_pop($d->currentHooks);
    }

    /**
     * Fire a filter hook — passes $value through each callback in priority order.
     * Callbacks signature: fn(mixed $value, ...$extra): mixed
     *
     * @param  mixed  $value   The value to filter (first argument, passed through)
     * @param  mixed  ...$args Extra read-only context passed to each callback
     * @return mixed           The filtered value
     */
    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        $d = static::getInstance();
        $d->currentHooks[] = $hook;

        $buckets = $d->filters[$hook] ?? [];
        ksort($buckets);
        foreach ($buckets as $callbacks) {
            foreach ($callbacks as $cb) {
                $value = $cb($value, ...$args);
            }
        }

        array_pop($d->currentHooks);
        return $value;
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    /** Return the name of the hook currently being fired (null if none). */
    public static function currentHook(): ?string
    {
        $d = static::getInstance();
        return empty($d->currentHooks) ? null : end($d->currentHooks);
    }

    /** Return all registered action hook names. */
    public static function registeredActions(): array
    {
        return array_keys(static::getInstance()->actions);
    }

    /** Return all registered filter hook names. */
    public static function registeredFilters(): array
    {
        return array_keys(static::getInstance()->filters);
    }
}
