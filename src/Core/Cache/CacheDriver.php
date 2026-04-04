<?php
declare(strict_types=1);
namespace Nemesis\Core\Cache;

interface CacheDriver {
    public function get($key, $default = null);
    public function set($key, $value, $seconds = 3600);
    public function forget($key);
    public function clear();
}
