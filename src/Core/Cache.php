<?php
declare(strict_types=1);
namespace Nemesis\Core;

class Cache {
    protected static $driver;

    protected static function driver() {
        if (self::$driver) return self::$driver;

        $driverName = (string) (Config::get('cache.default', getenv('CACHE_DRIVER') ?: 'file'));
        
        switch ($driverName) {
            case 'redis':
                self::$driver = new \Nemesis\Core\Cache\RedisCacheDriver(
                    Config::get('cache.stores.redis.host', getenv('REDIS_HOST') ?: '127.0.0.1'),
                    (int) Config::get('cache.stores.redis.port', getenv('REDIS_PORT') ?: 6379)
                );
                break;
            case 'database':
                self::$driver = new \Nemesis\Core\Cache\DatabaseCacheDriver();
                break;
            case 'array':
                self::$driver = new \Nemesis\Core\Cache\ArrayCacheDriver();
                break;
            case 'file':
            default:
                self::$driver = new \Nemesis\Core\Cache\FileCacheDriver(
                    (string) Config::get('cache.stores.file.path', base_path('storage/framework/cache'))
                );
                break;
        }

        return self::$driver;
    }

    public static function set(string $key, mixed $data, int $seconds = 3600): bool {
        return (bool) self::driver()->set(self::key($key), $data, $seconds);
    }

    public static function get(string $key, mixed $default = null): mixed {
        return self::driver()->get(self::key($key), $default);
    }

    public static function forget(string $key): bool {
        return (bool) self::driver()->forget(self::key($key));
    }

    public static function clear(): bool {
        return (bool) self::driver()->clear();
    }

    public static function has(string $key): bool {
        return self::driver()->has(self::key($key));
    }

    public static function remember(string $key, int $seconds, callable $callback): mixed {
        if (self::has($key)) {
            return self::get($key);
        }

        $value = $callback();
        self::set($key, $value, $seconds);
        return $value;
    }

    /** Replace the driver, primarily for application bootstraps and tests. */
    public static function setDriver(object $driver): void {
        if (!method_exists($driver, 'get') || !method_exists($driver, 'set') || !method_exists($driver, 'forget') || !method_exists($driver, 'clear') || !method_exists($driver, 'has')) {
            throw new \InvalidArgumentException('Cache driver does not implement the required cache operations.');
        }

        self::$driver = $driver;
    }

    private static function key(string $key): string {
        return (string) Config::get('cache.prefix', getenv('CACHE_PREFIX') ?: 'nemesis_') . $key;
    }
}
