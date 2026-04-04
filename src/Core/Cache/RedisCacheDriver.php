<?php
declare(strict_types=1);
namespace Nemesis\Core\Cache;

class RedisCacheDriver implements CacheDriver {
    protected $redis;

    public function __construct($host = '127.0.0.1', $port = 6379) {
        if (!class_exists('Redis')) {
            throw new \Exception("Redis extension not installed.");
        }
        $this->redis = new \Redis();
        $this->redis->connect($host, $port);
    }

    public function get($key, $default = null) {
        $value = $this->redis->get($key);
        return $value === false ? $default : unserialize($value);
    }

    public function set($key, $value, $seconds = 3600) {
        return $this->redis->set($key, serialize($value), $seconds);
    }

    public function forget($key) {
        $this->redis->del($key);
    }

    public function clear() {
        $this->redis->flushDB();
    }
}
