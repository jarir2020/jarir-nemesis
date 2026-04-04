<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 9 — Config Factory | Updated: 2026-04-03

namespace Nemesis\Config;

/**
 * Resolves typed Config DTOs by class name.
 *
 * Usage:
 *   $db = ConfigFactory::make(DatabaseConfig::class);
 *   echo $db->host;
 *
 * Or via DI container:
 *   $container->singleton(DatabaseConfig::class, fn() => ConfigFactory::make(DatabaseConfig::class));
 */
class ConfigFactory
{
    /** @var array<string, object> Singleton cache */
    private static array $cache = [];

    public static function make(string $class): object
    {
        if (isset(self::$cache[$class])) {
            return self::$cache[$class];
        }

        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Config class [{$class}] does not exist.");
        }

        if (!method_exists($class, 'fromEnv') && !method_exists($class, 'fromArray')) {
            throw new \InvalidArgumentException("Config class [{$class}] must have a fromEnv() or fromArray() static factory.");
        }

        $instance = method_exists($class, 'fromEnv')
            ? $class::fromEnv()
            : $class::fromArray([]);

        self::$cache[$class] = $instance;
        return $instance;
    }

    /** Flush the singleton cache (useful in tests). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Validate all required env keys for registered config classes.
     * Returns an array of missing key names.
     *
     * @param  string[]  $classes
     * @return string[]
     */
    public static function validateRequired(array $classes): array
    {
        $missing = [];
        foreach ($classes as $class) {
            if (method_exists($class, 'required')) {
                foreach ($class::required() as $key) {
                    if (empty(getenv($key))) {
                        $missing[] = $key;
                    }
                }
            }
        }
        return array_unique($missing);
    }
}
