<?php
declare(strict_types=1);

namespace Nemesis\Core\Cache;

/** In-memory cache driver intended for tests and short-lived workers. */
class ArrayCacheDriver implements CacheDriver
{
    /** @var array<string, array{value: mixed, expires_at: int}> */
    protected array $items = [];

    public function get($key, $default = null)
    {
        if (!isset($this->items[$key])) {
            return $default;
        }

        if (time() >= $this->items[$key]['expires_at']) {
            unset($this->items[$key]);
            return $default;
        }

        return $this->items[$key]['value'];
    }

    public function set($key, $value, $seconds = 3600)
    {
        $this->items[$key] = [
            'value' => $value,
            'expires_at' => time() + max(0, (int) $seconds),
        ];
        return true;
    }

    public function forget($key)
    {
        unset($this->items[$key]);
        return true;
    }

    public function clear()
    {
        $this->items = [];
        return true;
    }

    public function has($key)
    {
        $marker = new \stdClass();
        return $this->get($key, $marker) !== $marker;
    }
}
