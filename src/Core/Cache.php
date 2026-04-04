<?php
declare(strict_types=1);
namespace Nemesis\Core;

class Cache {
    protected static $driver;

    protected static function driver() {
        if (self::$driver) return self::$driver;

        $driverName = getenv('CACHE_DRIVER') ?: 'file';
        
        switch ($driverName) {
            case 'redis':
                self::$driver = new \Nemesis\Core\Cache\RedisCacheDriver(
                    getenv('REDIS_HOST') ?: '127.0.0.1',
                    getenv('REDIS_PORT') ?: 6379
                );
                break;
            case 'database':
                self::$driver = new \Nemesis\Core\Cache\DatabaseCacheDriver();
                break;
            case 'file':
            default:
                self::$driver = new \Nemesis\Core\Cache\FileCacheDriver(__DIR__ . '/../../storage/cache');
                break;
        }

        return self::$driver;
    }

    public static function set($key, $data, $seconds = 3600) {
        return self::driver()->set($key, $data, $seconds);
    }

    public static function get($key, $default = null) {
        return self::driver()->get($key, $default);
    }

    public static function forget($key) {
        return self::driver()->forget($key);
    }

    public static function clear() {
        return self::driver()->clear();
    }
}
