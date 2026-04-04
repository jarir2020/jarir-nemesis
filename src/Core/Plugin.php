<?php
declare(strict_types=1);
namespace Nemesis\Core;

/**
 * Plugin - Facade for plugin registration
 */
class Plugin {
    protected static $currentPlugin = null;
    protected static $hooks = [];
    protected static $middlewares = [];
    protected static $routes = [];
    protected static $commands = [];

    /**
     * Register a plugin
     */
    public static function register($name, callable $callback) {
        self::$currentPlugin = $name;
        $callback(new static());
        self::$currentPlugin = null;
    }

    /**
     * Register a hook
     */
    public static function hook($event, callable $callback) {
        if (!isset(self::$hooks[$event])) {
            self::$hooks[$event] = [];
        }
        
        self::$hooks[$event][] = [
            'plugin' => self::$currentPlugin,
            'callback' => $callback
        ];
    }

    /**
     * Fire a hook
     */
    public static function fire($event, ...$args) {
        if (!isset(self::$hooks[$event])) {
            return;
        }

        foreach (self::$hooks[$event] as $hook) {
            call_user_func_array($hook['callback'], $args);
        }
    }

    /**
     * Register middleware
     */
    public function middleware($class) {
        self::$middlewares[self::$currentPlugin][] = $class;
    }

    /**
     * Register routes
     */
    public function route($prefix, callable $callback) {
        self::$routes[self::$currentPlugin] = [
            'prefix' => $prefix,
            'callback' => $callback
        ];
    }

    /**
     * Register command
     */
    public function command($class) {
        self::$commands[self::$currentPlugin][] = $class;
    }

    /**
     * Get all registered middlewares
     */
    public static function getMiddlewares() {
        $all = [];
        foreach (self::$middlewares as $plugin => $middlewares) {
            $all = array_merge($all, $middlewares);
        }
        return $all;
    }

    /**
     * Get all registered routes
     */
    public static function getRoutes() {
        return self::$routes;
    }

    /**
     * Get all registered commands
     */
    public static function getCommands() {
        $all = [];
        foreach (self::$commands as $plugin => $commands) {
            $all = array_merge($all, $commands);
        }
        return $all;
    }

    /**
     * Get hooks for event
     */
    public static function getHooks($event) {
        return self::$hooks[$event] ?? [];
    }
}
