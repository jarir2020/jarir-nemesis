<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 4 — Route Model Binding | Created: 2026-04-02

namespace Nemesis\Router;

/**
 * RouteModelBinder
 *
 * Registers typed resolvers that automatically convert raw route parameter
 * strings into model/object instances before they reach the controller.
 *
 * Usage in routes/route.php:
 *   RouteModelBinder::bind('user', fn(string $id) => User::findOrFail((int)$id));
 *
 * When the router dispatches /users/42, the controller receives a User object
 * instead of the string "42".
 *
 * The Router calls RouteModelBinder::resolve() during dispatch, so you do not
 * need to register binders on both the binder and the Router — pick one style.
 * Using $router->bind() directly is the simpler approach for most cases.
 * This class is useful when binders are registered in ServiceProviders before
 * the Router is instantiated.
 */
class RouteModelBinder
{
    /** @var array<string, callable> */
    protected static array $binders = [];

    /**
     * Register a model binder for a route parameter name.
     *
     * @param string   $param    The route parameter name (e.g. 'user', 'post')
     * @param callable $resolver fn(string $value): mixed — returns the model
     */
    public static function bind(string $param, callable $resolver): void
    {
        self::$binders[$param] = $resolver;
    }

    /**
     * Resolve a route parameter value through its registered binder.
     * Returns the raw value unchanged if no binder is registered.
     */
    public static function resolve(string $param, string $value): mixed
    {
        if (isset(self::$binders[$param])) {
            return (self::$binders[$param])($value);
        }
        return $value;
    }

    /**
     * Apply all registered binders to the given params array.
     * Params that have no binder are left as-is.
     *
     * @param  array<string, string>  $params  Raw matched route params
     * @return array<string, mixed>            Resolved params
     */
    public static function resolveAll(array $params): array
    {
        $resolved = [];
        foreach ($params as $key => $value) {
            $resolved[$key] = isset(self::$binders[$key])
                ? (self::$binders[$key])($value)
                : $value;
        }
        return $resolved;
    }

    /**
     * Check whether a binder is registered for the given parameter.
     */
    public static function has(string $param): bool
    {
        return isset(self::$binders[$param]);
    }

    /**
     * Remove all registered binders (useful in tests).
     */
    public static function flush(): void
    {
        self::$binders = [];
    }

    /**
     * Return all registered binders (for inspection / testing).
     *
     * @return array<string, callable>
     */
    public static function all(): array
    {
        return self::$binders;
    }
}
